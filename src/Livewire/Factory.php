<?php

namespace Platform\Reporting\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;
use Platform\Core\Models\VerbalizationRecipe;
use Platform\Reporting\Verbalization\Channel\ChannelRendererRegistry;
use Platform\Reporting\Verbalization\Channel\PushChannelInterface;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorRegistry;
use Platform\Core\Verbalization\Template\TemplateRegistry;

/**
 * System-Baukasten-Uebersicht fuer den Verbalizer.
 *
 * Zeigt alle registrierten Bausteine (Subject-Collectors, Templates, Recipes,
 * EntityLinkProvider, Channel-Renderer) und markiert Luecken (Collector ohne
 * Template, leere metrics(), Kanal-Typen ohne Renderer, ...).
 *
 * Rein Diagnose — keine Konfiguration. Alle Aenderungen laufen weiter ueber
 * MCP-Tools. Ziel: den Ueberblick geben, aus dem heraus strategisch entschieden
 * werden kann was als naechstes gebaut wird.
 */
class Factory extends Component
{
    public string $activeSection = 'subjects';

    public function setSection(string $section): void
    {
        $this->activeSection = in_array($section, ['subjects', 'recipes', 'providers', 'channels'], true)
            ? $section
            : 'subjects';
    }

    // ────────── Subject-Types ──────────

    #[Computed]
    public function subjectTypes(): array
    {
        $collectors = $this->safe(fn () => app(SubjectCollectorRegistry::class)->all()) ?? [];
        $templates = $this->safe(fn () => $this->templateHandles()) ?? [];

        // Output-Statistik pro subject_type (letzte 30 Tage).
        $outputs = Schema::hasTable('verbalization_outputs')
            ? VerbalizationOutput::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->select('subject_type', DB::raw('COUNT(*) as cnt'))
                ->groupBy('subject_type')
                ->pluck('cnt', 'subject_type')
                ->all()
            : [];

        $items = [];
        foreach ($collectors as $type => $collector) {
            $items[] = [
                'type' => $type,
                'collector_class' => is_object($collector) ? get_class($collector) : (string) $collector,
                'template_registered' => in_array($type, $templates, true),
                'outputs_30d' => (int) ($outputs[$type] ?? 0),
                'sources' => $this->collectorDefaultSources($collector),
            ];
        }
        // Templates OHNE Collector — Kuriosität, aber sichtbar machen.
        foreach ($templates as $type) {
            if (! array_key_exists($type, $collectors)) {
                $items[] = [
                    'type' => $type,
                    'collector_class' => null,
                    'template_registered' => true,
                    'outputs_30d' => (int) ($outputs[$type] ?? 0),
                    'sources' => null,
                ];
            }
        }
        usort($items, fn ($a, $b) => strcmp($a['type'], $b['type']));
        return $items;
    }

    protected function templateHandles(): array
    {
        $reg = app(TemplateRegistry::class);
        // Registry hat kein oeffentliches all(); wir spiegeln via reflection.
        $ref = new \ReflectionClass($reg);
        foreach (['templates', 'handlers', 'byType'] as $propName) {
            if ($ref->hasProperty($propName)) {
                $p = $ref->getProperty($propName);
                $p->setAccessible(true);
                $data = $p->getValue($reg);
                if (is_array($data)) {
                    return array_keys($data);
                }
            }
        }
        return [];
    }

    protected function collectorDefaultSources(mixed $collector): ?array
    {
        if (! is_object($collector)) return null;
        try {
            $ref = new \ReflectionClass($collector);
            if ($ref->hasConstant('DEFAULT_SOURCES')) {
                $val = $ref->getConstant('DEFAULT_SOURCES');
                return is_array($val) ? array_keys($val) : null;
            }
        } catch (\Throwable $e) {}
        return null;
    }

    // ────────── Recipes ──────────

    #[Computed]
    public function recipesBySubject(): array
    {
        if (! Schema::hasTable('verbalization_recipes')) {
            return [];
        }
        $rows = VerbalizationRecipe::query()->orderBy('subject_type')->orderBy('key')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->subject_type][] = [
                'id' => $r->id,
                'key' => $r->key,
                'name' => $r->name,
                'scope' => $r->team_id ? 'team #' . $r->team_id : 'global',
                'include_natures' => $r->include_natures,
                'llm_provider' => $r->llm['provider'] ?? null,
                'llm_model' => $r->llm['model'] ?? null,
                'tone' => $r->style['tone'] ?? null,
                'address' => $r->style['address'] ?? null,
                'descend' => ($r->sources['descend'] ?? false) !== false,
                'is_active' => (bool) $r->is_active,
            ];
        }
        ksort($out);
        return $out;
    }

    // ────────── EntityLinkProvider ──────────

    #[Computed]
    public function entityLinkProviders(): array
    {
        try {
            $registry = app(\Platform\Organization\Services\EntityLinkRegistry::class);
        } catch (\Throwable $e) {
            return [];
        }

        $ref = new \ReflectionClass($registry);
        $providers = [];
        foreach (['providers'] as $propName) {
            if ($ref->hasProperty($propName)) {
                $p = $ref->getProperty($propName);
                $p->setAccessible(true);
                $providers = (array) $p->getValue($registry);
                break;
            }
        }

        $allDefs = [];
        try {
            $allDefs = (array) $registry->allMetricDefinitions();
        } catch (\Throwable $e) {}

        $items = [];
        foreach ($providers as $provider) {
            $aliases = $this->safe(fn () => (array) $provider->morphAliases()) ?? [];
            $hasMetricDefs = $provider instanceof \Platform\Organization\Contracts\HasMetricDefinitions;
            $metricKeys = $hasMetricDefs ? array_keys((array) $provider->metricDefinitions()) : [];

            // Metrik-Kandidat pro Alias
            $metricsSample = [];
            foreach ($aliases as $alias) {
                try {
                    $r = $provider->metrics($alias, [999999999 => []]); // leere Probe
                    $metricsSample[$alias] = is_array($r) ? 'callable' : 'no-array';
                } catch (\Throwable $e) {
                    $metricsSample[$alias] = 'error';
                }
            }

            // Dimensionen des Providers
            $dims = [];
            foreach ($metricKeys as $k) {
                $d = $allDefs[$k]['dimension'] ?? null;
                if ($d) $dims[$d] = true;
            }

            $items[] = [
                'class' => get_class($provider),
                'module' => $this->moduleFromClass(get_class($provider)),
                'aliases' => $aliases,
                'has_metric_defs' => $hasMetricDefs,
                'metrics_count' => count($metricKeys),
                'dimensions' => array_keys($dims),
                'metrics_callable' => ! empty($metricsSample),
            ];
        }
        usort($items, fn ($a, $b) => strcmp($a['module'], $b['module']));
        return $items;
    }

    protected function moduleFromClass(string $fqcn): string
    {
        // z.B. "Platform\Planner\Organization\PlannerEntityLinkProvider" → "planner"
        $parts = explode('\\', $fqcn);
        return isset($parts[1]) ? strtolower($parts[1]) : $fqcn;
    }

    // ────────── Kanal-Renderer ──────────

    #[Computed]
    public function channelRenderers(): array
    {
        try {
            $registry = app(ChannelRendererRegistry::class);
        } catch (\Throwable $e) {
            return [];
        }
        $renderers = (array) $registry->all();

        $activeCounts = Schema::hasTable('verbalization_channels')
            ? VerbalizationChannel::query()
                ->where('is_active', true)
                ->select('type', DB::raw('COUNT(*) as cnt'))
                ->groupBy('type')
                ->pluck('cnt', 'type')
                ->all()
            : [];

        // Katalog der bekannten Kanaltypen — verankert Diskussion "was moeglich, was da"
        $catalog = ['rss', 'web', 'obsidian', 'email', 'pdf', 'slack', 'webhook', 'voice'];

        $items = [];
        foreach ($catalog as $type) {
            $r = $renderers[$type] ?? null;
            $items[] = [
                'type' => $type,
                'registered' => $r !== null,
                'is_push' => $r instanceof PushChannelInterface,
                'class' => $r ? get_class($r) : null,
                'content_type' => $r?->contentType(),
                'active_count' => (int) ($activeCounts[$type] ?? 0),
            ];
        }
        // Zusaetzlich registrierte Typen die nicht im Katalog stehen
        foreach ($renderers as $type => $r) {
            if (! in_array($type, $catalog, true)) {
                $items[] = [
                    'type' => $type,
                    'registered' => true,
                    'is_push' => $r instanceof PushChannelInterface,
                    'class' => get_class($r),
                    'content_type' => $r->contentType(),
                    'active_count' => (int) ($activeCounts[$type] ?? 0),
                ];
            }
        }
        return $items;
    }

    // ────────── Helpers ──────────

    protected function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function render()
    {
        return view('reporting::livewire.factory')
            ->layout('platform::layouts.app');
    }
}
