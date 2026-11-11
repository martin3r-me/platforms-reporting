<?php

namespace Platform\Reporting\Reports;

use Illuminate\Support\Str;
use Platform\Core\Verbalization\GuardRails;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorRegistry;
use Platform\Reporting\Models\VerbalizationFeed;
use Platform\Reporting\Models\VerbalizationRecipe;
use Platform\Reporting\Verbalization\Feed\FeedService;
use Platform\Reporting\Verbalization\Verbalizer;

/**
 * Übersetzt zwischen dem Baukasten (ein flacher Entwurf) und der Engine
 * (Feed + Recipe). Keine eigenen Tabellen: ein Bericht IST ein Feed; eine
 * angepasste Perspektive ist eine team-eigene Recipe mit Schlüssel
 * „feed_{id}_{typ}", die auf ihrer Basis-Perspektive aufsetzt.
 *
 * Entwurf (array):
 *   title, description, subject_type,
 *   scope_mode (single|list|entity), scope_entity_id, scope_ids, descend,
 *   recipe_key, customize, address, tone, rhythm, natures[], since_window, extra_instruction,
 *   refresh_strategy, item_strategy, retention_items, access, is_active
 */
class ReportBuilder
{
    public const CUSTOM_PREFIX = 'feed_';

    public function __construct(
        protected SubjectCatalog $catalog,
        protected ReportScope $scope,
    ) {}

    // ── Perspektiven ─────────────────────────────────────────────

    /**
     * Wählbare Perspektiven (Recipes) für einen Typ — ohne die berichtseigenen Anpassungen.
     *
     * @return array<string, array{key:string,name:string,description:?string,natures:array,since_window:?string,style:array,scope:string}>
     */
    public function perspectives(?int $teamId, ?string $type): array
    {
        if (! $type) {
            return [];
        }

        $rows = VerbalizationRecipe::query()
            ->where('is_active', true)
            ->where('subject_type', $type)
            ->where('key', 'not like', str_replace('_', '\\_', self::CUSTOM_PREFIX) . '%')
            ->where(fn ($q) => $q->whereNull('team_id')->when($teamId, fn ($q) => $q->orWhere('team_id', $teamId)))
            ->orderBy('name')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            // Team-Variante schlägt die globale mit gleichem Schlüssel.
            if (isset($out[$r->key]) && $r->team_id === null) {
                continue;
            }
            $out[$r->key] = [
                'key' => $r->key,
                'name' => $r->name,
                'description' => $r->description,
                'natures' => $r->include_natures ?: ['state', 'movement', 'derivation'],
                'since_window' => $r->since_window,
                'style' => (array) $r->style,
                'scope' => $r->team_id ? 'team' : 'global',
            ];
        }

        return $out;
    }

    // ── Feed ↔ Entwurf ───────────────────────────────────────────

    public function draftFromFeed(VerbalizationFeed $feed): array
    {
        $recipes = (array) $feed->recipes;
        $type = $feed->subject_type ?: (array_key_first($recipes) ?? null);
        $selector = (array) $feed->subject_selector;
        $mode = $selector['mode'] ?? 'entity';
        $recipeKey = $type ? ($recipes[$type] ?? null) : null;

        $draft = array_merge($this->emptyDraft(), [
            'title' => (string) $feed->title,
            'description' => (string) $feed->description,
            'subject_type' => $type,
            'scope_mode' => $mode,
            'scope_entity_id' => $mode === 'entity'
                ? ($selector['entity_id'] ?? null)
                : ($mode === 'single' && $this->catalog->isEntityType($type) ? ($selector['id'] ?? null) : null),
            'scope_ids' => $mode === 'single' ? array_filter([$selector['id'] ?? null]) : (array) ($selector['ids'] ?? []),
            'descend' => ($selector['descend'] ?? false) !== false,
            'recipe_key' => $recipeKey,
            'refresh_strategy' => $feed->refresh_strategy === 'manual' ? 'on_request' : ($feed->refresh_strategy ?: 'on_request'),
            'item_strategy' => $feed->item_strategy ?: 'history',
            'retention_items' => (int) ($feed->retention_items ?: 50),
            'access' => $feed->access ?: 'public',
            'is_active' => (bool) $feed->is_active,
        ]);

        // Angepasste Perspektive → Basis + Abweichungen zurück in den Entwurf.
        if ($recipeKey && str_starts_with($recipeKey, self::CUSTOM_PREFIX)) {
            $custom = VerbalizationRecipe::query()
                ->where('key', $recipeKey)
                ->where('team_id', $feed->team_id)
                ->first();
            if ($custom) {
                $style = (array) $custom->style;
                $draft = array_merge($draft, [
                    'recipe_key' => $style['base_recipe'] ?? $recipeKey,
                    'customize' => true,
                    'address' => $style['address'] ?? 'sie',
                    'tone' => $style['tone'] ?? 'formal',
                    'rhythm' => $style['rhythm'] ?? 'short',
                    'extra_instruction' => (string) ($style['extra_instruction'] ?? ''),
                    'natures' => $custom->include_natures ?: ['state', 'movement', 'derivation'],
                    'since_window' => (string) ($custom->since_window ?? ''),
                ]);
            }
        }

        return $draft;
    }

    public function emptyDraft(): array
    {
        return [
            'title' => '',
            'description' => '',
            'subject_type' => null,
            'scope_mode' => 'entity',
            'scope_entity_id' => null,
            'scope_ids' => [],
            'descend' => true,
            'recipe_key' => null,
            'customize' => false,
            'address' => 'sie',
            'tone' => 'formal',
            'rhythm' => 'short',
            'natures' => ['state', 'movement', 'derivation'],
            'since_window' => '',
            'extra_instruction' => '',
            'refresh_strategy' => 'cron_weekly',
            'item_strategy' => 'history',
            'retention_items' => 50,
            'access' => 'public',
            'is_active' => true,
        ];
    }

    /** Stil-Felder einer Perspektive als Startwerte fürs Anpassen. */
    public function customizationDefaults(?int $teamId, array $draft): array
    {
        $p = $this->perspectives($teamId, $draft['subject_type'] ?? null)[$draft['recipe_key'] ?? ''] ?? null;
        if (! $p) {
            return [];
        }

        return [
            'address' => $p['style']['address'] ?? 'sie',
            'tone' => $p['style']['tone'] ?? 'formal',
            'rhythm' => $p['style']['rhythm'] ?? 'short',
            'extra_instruction' => (string) ($p['style']['extra_instruction'] ?? ''),
            'natures' => $p['natures'],
            'since_window' => (string) ($p['since_window'] ?? ''),
        ];
    }

    /** Unvollständig? Liefert die erste fehlende Angabe als Satz, sonst null. */
    public function missing(array $draft): ?string
    {
        if (! $draft['subject_type']) {
            return 'Wähle, worüber berichtet wird.';
        }
        if ($this->selector($draft) === null) {
            return 'Wähle den Ausschnitt — welche Einheit?';
        }
        if (! $draft['recipe_key']) {
            return 'Wähle eine Perspektive.';
        }

        return null;
    }

    /** subject_selector aus dem Entwurf; null wenn unvollständig. */
    public function selector(array $draft): ?array
    {
        $type = $draft['subject_type'];
        $mode = $draft['scope_mode'] ?? 'entity';

        if ($this->catalog->isEntityType($type)) {
            return $draft['scope_entity_id'] ? ['mode' => 'single', 'id' => (int) $draft['scope_entity_id']] : null;
        }
        if ($mode === 'single' && ! empty($draft['scope_ids'])) {
            return ['mode' => 'single', 'id' => reset($draft['scope_ids'])];
        }
        if ($mode === 'list' && ! empty($draft['scope_ids'])) {
            return ['mode' => 'list', 'ids' => array_values($draft['scope_ids'])];
        }
        if ($draft['scope_entity_id']) {
            return ['mode' => 'entity', 'entity_id' => (int) $draft['scope_entity_id'], 'descend' => (bool) $draft['descend']];
        }

        return null;
    }

    /**
     * Legt den Feed an bzw. aktualisiert ihn — inkl. angepasster Perspektive.
     */
    public function save(array $draft, ?VerbalizationFeed $feed, ?int $teamId, ?int $userId): VerbalizationFeed
    {
        $type = $draft['subject_type'];
        $feed ??= new VerbalizationFeed([
            'team_id' => $teamId,
            'owner_user_id' => $userId,
        ]);

        $recipes = (array) $feed->recipes;
        // Bisheriger Haupttyp raus, falls der Typ gewechselt wurde; weitere Typen (Mixed-Feeds) bleiben.
        if ($feed->subject_type && $feed->subject_type !== $type) {
            unset($recipes[$feed->subject_type]);
        }
        $recipes[$type] = $draft['recipe_key'];

        $feed->fill([
            'title' => trim($draft['title']) ?: $this->suggestTitle($draft),
            'description' => trim((string) $draft['description']) ?: null,
            'subject_type' => $type,
            'subject_selector' => $this->selector($draft),
            'recipes' => $recipes,
            'refresh_strategy' => $draft['refresh_strategy'],
            'item_strategy' => $draft['item_strategy'],
            'retention_items' => max(1, min(500, (int) $draft['retention_items'])),
            'access' => $draft['access'],
            'is_active' => (bool) $draft['is_active'],
        ]);
        $feed->save();

        $customKey = self::CUSTOM_PREFIX . $feed->id . '_' . $type;
        if ($draft['customize']) {
            $this->upsertCustomRecipe($customKey, $draft, $feed);
            $recipes[$type] = $customKey;
        } else {
            VerbalizationRecipe::query()->where('key', $customKey)->where('team_id', $feed->team_id)->delete();
        }

        if ($feed->recipes !== $recipes) {
            $feed->recipes = $recipes;
            $feed->save();
        }

        return $feed;
    }

    protected function upsertCustomRecipe(string $key, array $draft, VerbalizationFeed $feed): void
    {
        $base = $this->baseRecipeModel($draft['recipe_key'], $feed->team_id, $draft['subject_type']);

        VerbalizationRecipe::query()->updateOrCreate(
            ['key' => $key, 'team_id' => $feed->team_id],
            [
                'name' => Str::limit($feed->title, 80) . ' — angepasst',
                'description' => 'Angepasste Perspektive für den Bericht „' . $feed->title . '" (Basis: ' . ($base?->name ?? $draft['recipe_key']) . ').',
                'subject_type' => $draft['subject_type'],
                'sources' => $base?->sources ?? [],
                'style' => $this->customStyle($draft),
                'guards' => $base?->guards,
                'llm' => $base?->llm,
                'include_natures' => array_values($draft['natures']) ?: null,
                'since_window' => $draft['since_window'] ?: null,
                'freshness_requirement' => $base?->freshness_requirement,
                'is_active' => true,
                'created_by_user_id' => $feed->owner_user_id,
            ],
        );
    }

    protected function customStyle(array $draft): array
    {
        return [
            'address' => $draft['address'],
            'tone' => $draft['tone'],
            'rhythm' => $draft['rhythm'],
            'extra_instruction' => trim((string) $draft['extra_instruction']) ?: null,
            'base_recipe' => $draft['recipe_key'], // unbekannte Style-Keys ignoriert der Verbalizer
        ];
    }

    protected function baseRecipeModel(?string $key, ?int $teamId, ?string $type): ?VerbalizationRecipe
    {
        if (! $key) {
            return null;
        }

        return VerbalizationRecipe::query()
            ->where('key', $key)
            ->where('subject_type', $type)
            ->where(fn ($q) => $q->whereNull('team_id')->when($teamId, fn ($q) => $q->orWhere('team_id', $teamId)))
            ->orderByRaw('team_id IS NULL') // Team-Variante zuerst
            ->first();
    }

    /** Die Recipe, mit der der Entwurf gerade laufen würde (ungespeichert). */
    public function effectiveRecipe(array $draft, ?int $teamId): ?CollectionRecipe
    {
        $base = $this->baseRecipeModel($draft['recipe_key'], $teamId, $draft['subject_type']);
        if (! $base) {
            return null;
        }
        $recipe = CollectionRecipe::fromModel($base);
        if (! $draft['customize']) {
            return $recipe;
        }

        return new CollectionRecipe(
            key: $recipe->key,
            name: $recipe->name,
            description: $recipe->description,
            subjectType: $recipe->subjectType,
            sources: $recipe->sources,
            style: $this->customStyle($draft),
            guards: $recipe->guards,
            llm: $recipe->llm,
            includeNatures: array_values($draft['natures']) ?: null,
            sinceWindow: $draft['since_window'] ?: null,
            freshnessRequirement: $recipe->freshnessRequirement,
            teamId: $recipe->teamId,
        );
    }

    public function suggestTitle(array $draft): string
    {
        $what = $this->catalog->describe($draft['subject_type'])['label'];
        $where = $this->scope->entityName($draft['scope_entity_id'] ? (int) $draft['scope_entity_id'] : null);

        return $where ? "{$where} — {$what}" : $what;
    }

    // ── Ausschnitt & Vorschau ────────────────────────────────────

    /**
     * Subjects, die der Entwurf erfassen würde.
     *
     * @return \Illuminate\Support\Collection<int, array{type:string,id:mixed}>
     */
    public function subjects(array $draft, ?int $teamId)
    {
        $selector = $this->selector($draft);
        if (! $selector || ! $draft['subject_type']) {
            return collect();
        }

        $probe = new VerbalizationFeed([
            'subject_type' => $draft['subject_type'],
            'subject_selector' => $selector,
            'recipes' => [$draft['subject_type'] => $draft['recipe_key'] ?: 'preview'],
            'team_id' => $teamId,
        ]);

        return app(FeedService::class)->resolveSubjects($probe);
    }

    /**
     * Erzeugt einen Probe-Absatz für ein Subject — nichts wird gespeichert.
     *
     * @return array{prose:string,label:string,model:?string,facts:int}
     */
    public function preview(array $draft, mixed $team, array $subject): array
    {
        $teamId = $team?->id;
        $recipe = $this->effectiveRecipe($draft, $teamId);
        if (! $recipe) {
            throw new \RuntimeException('Perspektive nicht gefunden.');
        }
        $collector = app(SubjectCollectorRegistry::class)->resolve($subject['type']);
        if (! $collector) {
            throw new \RuntimeException('Für „' . $subject['type'] . '" ist kein Sammler registriert.');
        }

        $days = $recipe->sinceWindowDays();
        $collected = $collector->collectState($subject['id'], $recipe, $days > 0 ? now()->subDays($days) : now()->subDays(7));

        $result = app(Verbalizer::class)->verbalize(
            subject: $collected,
            style: ReportStyle::for($team, $subject['type']),
            rails: new GuardRails(),
            recipe: $recipe,
        );

        return [
            'prose' => $result->prose,
            'label' => (string) ($collected->identity->primaryName ?? $this->scope->subjectLabel($subject['type'], $subject['id'], $this->catalog)),
            'model' => $result->model,
            'facts' => count($collected->facts),
        ];
    }
}
