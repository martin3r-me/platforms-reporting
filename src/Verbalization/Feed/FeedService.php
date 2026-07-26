<?php

namespace Platform\Reporting\Verbalization\Feed;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;
use Platform\Core\Verbalization\GuardRails;
use Platform\Reporting\Verbalization\Recipe\RecipeResolver;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorRegistry;
use Platform\Reporting\Verbalization\Verbalizer;

/**
 * Orchestriert die Feed-Pipeline:
 *
 *   1. Subjects aufloesen aus subject_selector (single / list / entity)
 *   2. Pro Subject die passende Recipe aus feed.recipes[subject_type] holen
 *   3. Den passenden Collector aus der Registry rufen
 *   4. Verbalizer aufrufen (mit Recipe, Style, Guards, ggf. Provider/Modell-Override)
 *   5. Output in verbalization_outputs persistieren (mit feed_id, timestamps)
 *
 * Entity-Selektor-Aufloesung ist optional gekoppelt — der Service nutzt
 * organization-Tabellen direkt via DB-Query (kein harter Modul-Zwang per
 * Class-Reference, sondern nur Tabellen-Existenz-Check).
 */
class FeedService
{
    public function __construct(
        protected SubjectCollectorRegistry $collectors,
        protected RecipeResolver $recipes,
        protected Verbalizer $verbalizer,
        protected ?\Platform\Reporting\Verbalization\Channel\ChannelRendererRegistry $channels = null,
    ) {}

    /**
     * Resolviert die Subjects fuer einen Feed.
     * Output: Collection<array{type:string, id:string|int}>
     */
    public function resolveSubjects(VerbalizationFeed $feed): Collection
    {
        $selector = $feed->subject_selector ?? [];
        $mode = $selector['mode'] ?? null;
        $explicitType = $feed->subject_type;

        return match ($mode) {
            'single' => $this->resolveSingle($selector, $explicitType),
            'list' => $this->resolveList($selector, $explicitType),
            'entity' => $this->resolveByEntity($selector, $feed->recipes ?? []),
            default => collect(),
        };
    }

    protected function resolveSingle(array $selector, ?string $type): Collection
    {
        $id = $selector['id'] ?? null;
        if (! $id || ! $type) {
            return collect();
        }
        return collect([['type' => $type, 'id' => $id]]);
    }

    protected function resolveList(array $selector, ?string $type): Collection
    {
        $ids = $selector['ids'] ?? [];
        if (empty($ids) || ! $type) {
            return collect();
        }
        return collect($ids)->map(fn ($id) => ['type' => $type, 'id' => $id])->values();
    }

    /**
     * Entity-Mode: alle dimension_links der Organization-Entity, gefiltert auf
     * jene linkable_types, fuer die der Feed eine Recipe definiert hat.
     *
     * linkable_type-Mapping zu subject_type:
     *   "project"          → "planner_project"   (Planner nutzt 'project' als morph-map alias)
     *   "helpdesk_board"   → "helpdesk_board"
     *   ...
     * Wir gehen pragmatisch davon aus, dass linkable_type == subject_type, mit
     * einer kleinen Aliase-Map fuer Sonderfaelle.
     *
     * Rekursion: subject_selector.descend (false | true | int) sammelt zusaetzlich
     * DimensionLinks aller Descendants ueber parent_entity_id. Damit greift ein
     * Feed am Kunden-Knoten automatisch auf alle Sub-Ebenen (Engagements,
     * Initiativen, ...), ohne pro Ebene konfigurieren zu muessen.
     */
    protected function resolveByEntity(array $selector, array $recipesMap): Collection
    {
        $entityId = $selector['entity_id'] ?? null;
        if (! $entityId || empty($recipesMap)) {
            return collect();
        }

        // Sicherheit: Tabelle existiert?
        if (! \Schema::hasTable('organization_dimension_links')) {
            return collect();
        }

        // Welche linkable_types sind im Feed konfiguriert?
        $configuredTypes = array_keys($recipesMap);
        $aliases = $this->linkableTypeAliases();
        // Erweitere mit Aliasen — beide Richtungen, damit DB-Query stimmt.
        $dbTypes = [];
        foreach ($configuredTypes as $subjectType) {
            $dbTypes[] = $subjectType;
            $alias = array_search($subjectType, $aliases, true);
            if ($alias !== false) {
                $dbTypes[] = $alias;
            }
        }
        $dbTypes = array_values(array_unique($dbTypes));

        // dimension_links → dimension_value → metadata.source_entity_id.
        // Es gibt kein FK-Feld v.entity_id; die Entity-Verknuepfung steht im
        // JSON-Feld metadata. Wir lesen sie ueber den JSON-Pfad.
        if (! \Schema::hasTable('organization_dimension_values')) {
            return collect();
        }

        // Rekursion: Root + optional Descendants.
        $descend = $selector['descend'] ?? false;
        $entityScope = $this->collectEntityScope((int) $entityId, $descend);

        $links = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(v.metadata, '$.source_entity_id')) AS UNSIGNED)"), $entityScope)
            ->whereIn('l.linkable_type', $dbTypes)
            ->select('l.linkable_type', 'l.linkable_id')
            ->distinct()
            ->get();

        // Fallback: alte organization_entity_links-Tabelle (deprecated, fuer Rollback noch da).
        if ($links->isEmpty() && \Schema::hasTable('organization_entity_links')) {
            $links = DB::table('organization_entity_links')
                ->whereIn('entity_id', $entityScope)
                ->whereIn('linkable_type', $dbTypes)
                ->select('linkable_type', 'linkable_id')
                ->distinct()
                ->get();
        }

        return $links->map(function ($row) use ($aliases) {
            $subjectType = $aliases[$row->linkable_type] ?? $row->linkable_type;
            return ['type' => $subjectType, 'id' => $row->linkable_id];
        })->values();
    }

    /**
     * Traversiert den Entity-Baum breitenfirst und liefert alle Entity-IDs im Scope.
     * $descend: false = nur Root; true = alle Descendants; int = bis zu N Ebenen tief.
     *
     * @return int[]
     */
    protected function collectEntityScope(int $rootId, mixed $descend): array
    {
        if ($descend === false || $descend === null) {
            return [$rootId];
        }
        if (! \Schema::hasTable('organization_entities')) {
            return [$rootId];
        }
        $maxDepth = ($descend === true) ? null : max(0, (int) $descend);

        $visited = [$rootId => true];
        $result = [$rootId];
        $queue = [[$rootId, 0]];

        while (! empty($queue)) {
            [$id, $depth] = array_shift($queue);
            if ($maxDepth !== null && $depth >= $maxDepth) {
                continue;
            }
            $children = DB::table('organization_entities')
                ->where('parent_entity_id', $id)
                ->pluck('id')
                ->all();
            foreach ($children as $cid) {
                $cid = (int) $cid;
                if (isset($visited[$cid])) {
                    continue;
                }
                $visited[$cid] = true;
                $result[] = $cid;
                $queue[] = [$cid, $depth + 1];
            }
        }
        return $result;
    }

    /**
     * @return array<string, string>  linkable_type → subject_type
     */
    protected function linkableTypeAliases(): array
    {
        return [
            'project' => 'planner_project',   // Planner nutzt 'project' im Morph-Map
        ];
    }

    /**
     * Generiert die Verbalisierungen fuer alle Subjects des Feeds und persistiert sie.
     *
     * Dedup: vor jedem LLM-Call wird der state_hash des Subjects mit dem juengsten
     * Output (pro feed+subject) verglichen. Bei Gleichheit wird kein neuer Output
     * erzeugt — Reader bekommen sonst Spam.
     *
     * @return array{outputs_created:int, skipped_no_change:int, subjects_resolved:int, errors:array}
     */
    public function refresh(VerbalizationFeed $feed, ?StyleProfile $baseStyle = null, bool $force = false): array
    {
        $subjects = $this->resolveSubjects($feed);
        $errors = [];
        $created = 0;
        $skipped = 0;

        foreach ($subjects as $s) {
            try {
                $collector = $this->collectors->resolve($s['type']);
                if (! $collector) {
                    $errors[] = "Kein Collector fuer subject_type '{$s['type']}'.";
                    continue;
                }

                $recipeKey = $feed->recipes[$s['type']] ?? null;
                if (! $recipeKey) {
                    $errors[] = "Keine Recipe fuer subject_type '{$s['type']}' in Feed konfiguriert.";
                    continue;
                }

                $recipe = $this->recipes->resolve($recipeKey, $feed->team_id, $s['type']);
                if (! $recipe) {
                    $errors[] = "Recipe '{$recipeKey}' nicht gefunden.";
                    continue;
                }

                // Zuerst den juengsten Output holen — dessen created_at ist der Fallback-
                // "since"-Zeitpunkt (bisheriges Verhalten). Wenn die Recipe ein
                // sinceWindow deklariert (z.B. "7d"), gewinnt das: der Sammler bekommt
                // dann konsequent now()-N Tage, unabhaengig vom letzten Refresh. Damit
                // sind Wochen-/Tages-/Monats-Berichte semantisch klar.
                $lastOutput = VerbalizationOutput::where('feed_id', $feed->id)
                    ->where('subject_type', $s['type'])
                    ->where('subject_id', (string) $s['id'])
                    ->orderByDesc('created_at')
                    ->first();
                $windowDays = $recipe->sinceWindowDays();
                $since = $windowDays > 0
                    ? now()->subDays($windowDays)
                    : $lastOutput?->created_at;

                $subject = $collector->collectState($s['id'], $recipe, $since);
                $stateHash = $subject->stateHash();

                // Dedup: wenn der juengste Output denselben Hash hat, ueberspringen —
                // kein Delta = kein neuer Output. Mit $force wird der Skip uebergangen,
                // z.B. wenn sich das Template oder Prompt geaendert hat (State gleich,
                // aber Rendering anders).
                if (! $force && $lastOutput && $lastOutput->state_hash === $stateHash) {
                    $skipped++;
                    continue;
                }

                // Movement-Gate: wenn Recipe.sources.skip_if_no_movement true ist und
                // KEIN einziger MOVEMENT-Fact im Subject entstanden ist, kein Bericht.
                // Semantik: "sag nur was, wenn was passiert ist". Wirkt auch bei
                // Snapshot-Drift, die den state_hash minimal aendert obwohl inhaltlich
                // nichts Neues zu sagen waere.
                if (! $force && (bool) ($recipe->sources['skip_if_no_movement'] ?? false)) {
                    $hasMovement = false;
                    foreach ($subject->facts as $f) {
                        if ($f->nature === \Platform\Core\Verbalization\Enums\FactNature::MOVEMENT) {
                            $hasMovement = true;
                            break;
                        }
                    }
                    if (! $hasMovement) {
                        $skipped++;
                        continue;
                    }
                }

                $result = $this->verbalizer->verbalize(
                    subject: $subject,
                    style: $baseStyle ?? StyleProfile::formal(),
                    rails: new GuardRails(),
                    providerKey: $feed->llm_provider,
                    modelOverride: $feed->llm_model,
                    recipe: $recipe,
                );

                $output = VerbalizationOutput::create([
                    'feed_id' => $feed->id,
                    'recipe_key' => $recipe->key,
                    'subject_type' => $subject->type,
                    'subject_id' => (string) $subject->id,
                    'subject_label' => $subject->identity->primaryName,
                    'prose' => $result->prose,
                    'llm_provider' => $result->meta['provider'] ?? null,
                    'llm_model' => $result->model,
                    'input_tokens' => $result->usage['input_tokens'] ?? null,
                    'output_tokens' => $result->usage['output_tokens'] ?? null,
                    'state_hash' => $stateHash,
                    'team_id' => $feed->team_id,
                ]);
                $created++;

                // Baseline-Snapshot: sobald der Collector einen Metric-Bag im
                // Subject.meta mitliefert, wird der aktuelle Stand persistiert.
                // Damit hat der naechste Refresh eine echte Vergleichsbasis.
                $metricsBag = $subject->meta['metrics_bag'] ?? null;
                if (is_array($metricsBag) && ! empty($metricsBag)) {
                    try {
                        app(\Platform\Reporting\Verbalization\Baseline\BaselineService::class)
                            ->snapshot($subject->type, (string) $subject->id, $metricsBag);
                    } catch (\Throwable $e) {
                        Log::warning('[FeedService] baseline snapshot failed', [
                            'feed_id' => $feed->id,
                            'subject' => $subject->type . '#' . $subject->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Push-Kanaele bedienen — Obsidian schreibt eine MD-Datei, Email
                // versendet, Slack pusht ins Webhook. Renderer-Ausfaelle einzelner
                // Kanaele killen den Bericht nicht: Fehler werden geloggt und
                // gesammelt, aber der Output ist bereits persistiert.
                $errors = array_merge(
                    $errors,
                    $this->deliverPushChannels($feed, $output),
                );
            } catch (\Throwable $e) {
                Log::warning('[FeedService] verbalize failed', [
                    'feed_id' => $feed->id,
                    'subject' => $s,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "{$s['type']}#{$s['id']}: " . $e->getMessage();
            }
        }

        $feed->last_refreshed_at = now();
        $feed->save();

        // Retention: alte Outputs ueber retention_items hinaus loeschen
        $this->enforceRetention($feed);

        return [
            'outputs_created' => $created,
            'skipped_no_change' => $skipped,
            'subjects_resolved' => $subjects->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Ruft alle aktiven Push-Kanaele des Feeds mit dem frischen Output auf.
     * Fehler pro Kanal werden gesammelt und zurueckgegeben, killen aber nicht
     * den Refresh — der Output ist bereits persistiert, andere Kanaele laufen weiter.
     *
     * @return string[]  Fehlermeldungen (leer wenn alles gut)
     */
    protected function deliverPushChannels(
        VerbalizationFeed $feed,
        VerbalizationOutput $output,
    ): array {
        if (! $this->channels) {
            return [];
        }

        $errors = [];
        $channels = \Platform\Core\Models\VerbalizationChannel::where('verbalization_feed_id', $feed->id)
            ->where('is_active', true)
            ->get();

        foreach ($channels as $channel) {
            $renderer = $this->channels->resolve($channel->type);
            if (! $renderer instanceof \Platform\Reporting\Verbalization\Channel\PushChannelInterface) {
                // Pull-Kanaele oder unbekannter Typ — nichts zu tun.
                continue;
            }

            try {
                $r = $renderer->deliver($channel, $feed, $output);
                if (! empty($r['success'])) {
                    $channel->last_delivered_at = now();
                    $channel->save();
                } else {
                    $errors[] = "channel[{$channel->type}#{$channel->id}]: " . ($r['error'] ?? 'unknown');
                }
            } catch (\Throwable $e) {
                Log::warning('[FeedService] channel deliver failed', [
                    'channel_id' => $channel->id,
                    'channel_type' => $channel->type,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "channel[{$channel->type}#{$channel->id}]: " . $e->getMessage();
            }
        }
        return $errors;
    }

    protected function enforceRetention(VerbalizationFeed $feed): void
    {
        $limit = (int) ($feed->retention_items ?? 50);
        if ($limit <= 0) {
            return;
        }
        // Bei "snapshot"-Strategy: pro Subject nur das juengste aufheben
        if ($feed->item_strategy === 'snapshot') {
            $latestIds = DB::table('verbalization_outputs as a')
                ->where('a.feed_id', $feed->id)
                ->whereRaw('a.created_at = (
                    SELECT MAX(b.created_at) FROM verbalization_outputs b
                    WHERE b.feed_id = a.feed_id
                      AND b.subject_type = a.subject_type
                      AND b.subject_id = a.subject_id
                )')
                ->pluck('a.id');
            VerbalizationOutput::where('feed_id', $feed->id)
                ->whereNotIn('id', $latestIds)
                ->delete();
            return;
        }
        // history: nur die letzten N total
        $keepIds = VerbalizationOutput::where('feed_id', $feed->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');
        VerbalizationOutput::where('feed_id', $feed->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Die Items, die der RSS-Renderer fuer einen Feed zeigen soll.
     */
    public function itemsForFeed(VerbalizationFeed $feed): Collection
    {
        $limit = (int) ($feed->retention_items ?? 50);

        if ($feed->item_strategy === 'snapshot') {
            // pro Subject nur juengster Output
            $latestIds = DB::table('verbalization_outputs as a')
                ->where('a.feed_id', $feed->id)
                ->whereRaw('a.created_at = (
                    SELECT MAX(b.created_at) FROM verbalization_outputs b
                    WHERE b.feed_id = a.feed_id
                      AND b.subject_type = a.subject_type
                      AND b.subject_id = a.subject_id
                )')
                ->pluck('a.id');
            return VerbalizationOutput::whereIn('id', $latestIds)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        return VerbalizationOutput::where('feed_id', $feed->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
