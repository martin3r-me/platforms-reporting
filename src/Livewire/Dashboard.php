<?php

namespace Platform\Reporting\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Reporting\Models\VerbalizationFeed;
use Platform\Reporting\Models\VerbalizationOutput;
use Platform\Reporting\Models\VerbalizationRecipe;
use Platform\Reporting\Reports\ReportBuilder;
use Platform\Reporting\Reports\ReportScope;
use Platform\Reporting\Reports\SubjectCatalog;

/**
 * Berichte — Startseite des Moduls: alle Berichte des Teams (+ globale),
 * je mit Ausschnitt, Rhythmus und dem Anfang der jüngsten Ausgabe.
 */
class Dashboard extends Component
{
    #[Url(as: 'f')]
    public string $filter = 'active';

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['active', 'all'], true) ? $filter : 'active';
        unset($this->reports);
    }

    #[Computed]
    public function reports(): array
    {
        $teamId = Auth::user()?->currentTeam?->id;

        $feeds = VerbalizationFeed::query()
            ->where(fn ($q) => $q->whereNull('team_id')->when($teamId, fn ($q) => $q->orWhere('team_id', $teamId)))
            ->when($this->filter === 'active', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('last_refreshed_at')
            ->orderByDesc('created_at')
            ->get();

        if ($feeds->isEmpty()) {
            return [];
        }

        $ids = $feeds->pluck('id')->all();
        $counts = VerbalizationOutput::query()
            ->whereIn('feed_id', $ids)
            ->select('feed_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('feed_id')
            ->pluck('cnt', 'feed_id');
        $latestIds = VerbalizationOutput::query()
            ->whereIn('feed_id', $ids)
            ->select('feed_id', DB::raw('MAX(id) as id'))
            ->groupBy('feed_id')
            ->pluck('id', 'feed_id');
        $latest = VerbalizationOutput::query()->whereIn('id', $latestIds->values())->get()->keyBy('feed_id');

        $catalog = app(SubjectCatalog::class);
        $entityIds = $feeds->map(function ($f) use ($catalog) {
            $sel = (array) $f->subject_selector;

            return $sel['entity_id']
                ?? (($sel['mode'] ?? null) === 'single' && $catalog->isEntityType($f->subject_type) ? ($sel['id'] ?? null) : null);
        })->filter()->all();
        $entityNames = app(ReportScope::class)->entityNames($entityIds);

        $recipeKeys = $feeds->flatMap(fn ($f) => array_values((array) $f->recipes))->unique()->all();
        $recipeNames = VerbalizationRecipe::query()->whereIn('key', $recipeKeys)->pluck('name', 'key');

        $rhythm = ['cron_daily' => 'täglich', 'cron_weekly' => 'wöchentlich'];

        return $feeds->map(function (VerbalizationFeed $f) use ($catalog, $counts, $latest, $entityNames, $recipeNames, $rhythm, $teamId) {
            $type = $f->subject_type ?: array_key_first((array) $f->recipes);
            $info = $catalog->describe($type);
            $sel = (array) $f->subject_selector;
            $entityId = $sel['entity_id'] ?? (($sel['mode'] ?? null) === 'single' && $info['kind'] === 'entity' ? ($sel['id'] ?? null) : null);
            $recipeKey = ((array) $f->recipes)[$type] ?? null;

            $where = $entityId
                ? ($entityNames[$entityId] ?? 'Einheit #' . $entityId) . (! empty($sel['descend']) && $info['kind'] !== 'entity' ? ' + Untereinheiten' : '')
                : (($sel['mode'] ?? null) === 'list' ? count($sel['ids'] ?? []) . ' feste Einträge' : 'ein fester Eintrag');
            $perspective = $recipeKey && str_starts_with($recipeKey, ReportBuilder::CUSTOM_PREFIX)
                ? 'eigene Perspektive'
                : ($recipeNames[$recipeKey] ?? $recipeKey);

            $last = $latest[$f->id] ?? null;

            return [
                'id' => $f->id,
                'title' => $f->title,
                'icon' => $info['icon'],
                'summary' => implode(' · ', array_filter([$info['label'], $where, $perspective, $rhythm[$f->refresh_strategy] ?? 'auf Abruf'])),
                'excerpt' => $last ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(\Illuminate\Support\Str::markdown((string) $last->prose)), ENT_QUOTES | ENT_HTML5))), 220) : null,
                'last' => $f->last_refreshed_at?->diffForHumans(),
                'outputs' => (int) ($counts[$f->id] ?? 0),
                'active' => (bool) $f->is_active,
                'global' => $f->team_id === null,
            ];
        })->all();
    }

    #[Computed]
    public function stats(): array
    {
        $teamId = Auth::user()?->currentTeam?->id;
        $feedIds = VerbalizationFeed::query()
            ->where(fn ($q) => $q->whereNull('team_id')->when($teamId, fn ($q) => $q->orWhere('team_id', $teamId)))
            ->pluck('is_active', 'id');

        return [
            'active' => $feedIds->filter()->count(),
            'scheduled' => VerbalizationFeed::query()->whereIn('id', $feedIds->keys())->where('is_active', true)
                ->whereIn('refresh_strategy', ['cron_daily', 'cron_weekly'])->count(),
            'outputs_30d' => VerbalizationOutput::query()->whereIn('feed_id', $feedIds->keys())
                ->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    public function render()
    {
        return view('reporting::livewire.dashboard')
            ->layout('platform::layouts.app');
    }
}
