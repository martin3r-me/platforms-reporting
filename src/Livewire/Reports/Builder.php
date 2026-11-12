<?php

namespace Platform\Reporting\Livewire\Reports;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reporting\Jobs\RefreshReportJob;
use Platform\Reporting\Models\VerbalizationFeed;
use Platform\Reporting\Models\VerbalizationOutput;
use Platform\Reporting\Models\VerbalizationRecipe;
use Platform\Reporting\Reports\ReportBuilder;
use Platform\Reporting\Reports\ReportScope;
use Platform\Reporting\Reports\SubjectCatalog;

/**
 * Bericht-Baukasten: worüber · Ausschnitt · Perspektive · Rhythmus · Ausgabe,
 * rechts daneben eine echte Probe (LLM-Lauf, nicht gespeichert).
 */
class Builder extends Component
{
    public ?int $feedId = null;

    public array $draft = [];

    public string $entitySearch = '';

    /** Anzahl Subjects im Ausschnitt; null = noch nicht ermittelt/unvollständig. */
    public ?int $scopeCount = null;

    public ?array $preview = null;

    public ?string $previewError = null;

    public int $previewIndex = 0;

    /** Kurze Rückmeldung nach Speichern/Erzeugen (inline statt Toast). */
    public ?string $status = null;

    public function mount(?VerbalizationFeed $feed = null): void
    {
        $builder = app(ReportBuilder::class);

        if ($feed && $feed->exists) {
            abort_unless($this->visible($feed), 404);
            $this->feedId = $feed->id;
            $this->draft = $builder->draftFromFeed($feed);
            $this->status = session('reporting.saved');
        } else {
            $this->draft = $builder->emptyDraft();
            // Einstieg aus organization: ?entity=ID wählt den Ausschnitt vor.
            $entityId = (int) request()->query('entity');
            if (app(ReportScope::class)->entityInTeam($entityId, $this->teamId())) {
                $this->draft['scope_entity_id'] = $entityId;
            }
            $types = array_keys($this->catalog);
            $type = (string) request()->query('type');
            if (in_array($type, $types, true) || count($types) === 1) {
                $this->chooseType(in_array($type, $types, true) ? $type : $types[0]);
            }
        }

        $this->recount();
    }

    // ── Auswahl ──────────────────────────────────────────────────

    public function chooseType(string $type): void
    {
        if (! isset($this->catalog[$type]) || ! $this->canEdit) {
            return;
        }
        if (($this->draft['subject_type'] ?? null) !== $type) {
            $this->draft['subject_type'] = $type;
            $this->draft['scope_mode'] = 'entity';
            $this->draft['scope_ids'] = [];
            $this->draft['customize'] = false;
            $perspectives = app(ReportBuilder::class)->perspectives($this->teamId(), $type);
            $this->draft['recipe_key'] = array_key_first($perspectives);
            unset($this->perspectives);
        }
        $this->resetPreview();
        $this->recount();
    }

    public function chooseEntity(int $id): void
    {
        if (! $this->canEdit || ! app(ReportScope::class)->entityInTeam($id, $this->teamId())) {
            return;
        }
        $this->draft['scope_entity_id'] = $id;
        $this->draft['scope_mode'] = 'entity';
        $this->draft['scope_ids'] = [];
        $this->entitySearch = '';
        $this->resetPreview();
        $this->recount();
    }

    public function clearEntity(): void
    {
        $this->draft['scope_entity_id'] = null;
        $this->draft['scope_mode'] = 'entity';
        $this->draft['scope_ids'] = [];
        $this->resetPreview();
        $this->recount();
    }

    public function choosePerspective(string $key): void
    {
        if (! isset($this->perspectives[$key]) || ! $this->canEdit) {
            return;
        }
        $this->draft['recipe_key'] = $key;
        if ($this->draft['customize']) {
            $this->draft = array_merge($this->draft, app(ReportBuilder::class)->customizationDefaults($this->teamId(), $this->draft));
        }
        $this->resetPreview();
    }

    public function toggleCustomize(): void
    {
        if (! $this->canEdit) {
            return;
        }
        $this->draft['customize'] = ! $this->draft['customize'];
        if ($this->draft['customize']) {
            $this->draft = array_merge($this->draft, app(ReportBuilder::class)->customizationDefaults($this->teamId(), $this->draft));
        }
        $this->resetPreview();
    }

    public function toggleNature(string $nature): void
    {
        if (! in_array($nature, ['state', 'movement', 'derivation'], true)) {
            return;
        }
        $natures = (array) $this->draft['natures'];
        $natures = in_array($nature, $natures, true)
            ? array_values(array_diff($natures, [$nature]))
            : array_values(array_merge($natures, [$nature]));
        // Mindestens eine Art bleibt — ein Bericht ohne Fakten wäre leer.
        $this->draft['natures'] = $natures ?: [$nature];
        $this->resetPreview();
    }

    public function updatedDraft($value, $key): void
    {
        if (in_array($key, ['descend', 'scope_mode'], true)) {
            $this->recount();
        }
        if (in_array($key, ['descend', 'address', 'tone', 'rhythm', 'since_window', 'extra_instruction'], true)) {
            $this->resetPreview();
        }
    }

    // ── Vorschau ─────────────────────────────────────────────────

    public function generatePreview(): void
    {
        $builder = app(ReportBuilder::class);
        $this->resetPreview();

        if ($missing = $builder->missing($this->draft)) {
            $this->previewError = $missing;

            return;
        }

        $subjects = $builder->subjects($this->draft, $this->teamId())->values();
        if ($subjects->isEmpty()) {
            $this->previewError = 'Im Ausschnitt liegt nichts, worüber berichtet werden kann.';

            return;
        }

        $index = $this->previewIndex % $subjects->count();
        try {
            $this->preview = $builder->preview($this->draft, $this->team(), $subjects[$index]) + [
                'position' => $index + 1,
                'total' => $subjects->count(),
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Team-Scopes der Module: der Eintrag liegt in einem anderen Team als dem aktuellen.
            $this->previewError = 'Probe nicht möglich: Der Eintrag liegt in einem anderen Team und ist aus deinem aktuellen Team nicht lesbar. Wechsle ins Team des Eintrags — der geplante Lauf ist davon nicht betroffen.';
        } catch (\Throwable $e) {
            $this->previewError = 'Probe fehlgeschlagen: ' . Str::limit($e->getMessage(), 200);
        }
    }

    public function nextPreview(): void
    {
        $this->previewIndex++;
        $this->generatePreview();
    }

    protected function resetPreview(): void
    {
        $this->preview = null;
        $this->previewError = null;
    }

    // ── Speichern & Aktionen ─────────────────────────────────────

    public function save(): void
    {
        abort_unless($this->canEdit, 403);
        $builder = app(ReportBuilder::class);

        $this->validate([
            'draft.title' => 'nullable|string|max:200',
            'draft.description' => 'nullable|string|max:2000',
            'draft.extra_instruction' => 'nullable|string|max:2000',
            'draft.retention_items' => 'required|integer|min:1|max:500',
            'draft.refresh_strategy' => 'required|in:on_request,cron_daily,cron_weekly',
            'draft.item_strategy' => 'required|in:history,snapshot',
            'draft.access' => 'required|in:public,team',
        ]);

        if ($missing = $builder->missing($this->draft)) {
            $this->addError('draft', $missing);

            return;
        }

        // Einheit nur aus dem eigenen Team — außer sie war schon so gespeichert.
        $entityId = $this->draft['scope_entity_id'] ? (int) $this->draft['scope_entity_id'] : null;
        $stored = (array) ($this->feed?->subject_selector ?? []);
        $unchanged = $entityId && in_array($entityId, [(int) ($stored['entity_id'] ?? 0), (int) ($stored['id'] ?? 0)], true);
        if ($entityId && ! $unchanged && ! app(ReportScope::class)->entityInTeam($entityId, $this->teamId())) {
            $this->addError('draft', 'Diese Einheit gehört nicht zu deinem Team.');

            return;
        }

        $isNew = $this->feedId === null;
        $feed = $builder->save($this->draft, $this->feed, $this->teamId(), Auth::id());
        $this->feedId = $feed->id;
        $this->draft['title'] = $feed->title;
        unset($this->feed, $this->outputs);

        if ($isNew) {
            session()->flash('reporting.saved', 'Bericht angelegt.');
            $this->redirectRoute('reporting.reports.edit', ['feed' => $feed->id], navigate: true);

            return;
        }

        $this->status = 'Gespeichert.';
    }

    public function refreshNow(): void
    {
        abort_unless($this->canEdit && $this->feed, 403);
        RefreshReportJob::dispatch($this->feed->id, true);
        $this->status = 'Wird im Hintergrund erzeugt — die Ausgaben erscheinen unten, sobald sie fertig sind.';
    }

    public function delete(): void
    {
        abort_unless($this->canEdit && $this->feed, 403);
        $feed = $this->feed;
        VerbalizationRecipe::query()
            ->where('key', 'like', ReportBuilder::CUSTOM_PREFIX . $feed->id . '\_%')
            ->where('team_id', $feed->team_id)
            ->delete();
        $feed->delete();

        $this->redirectRoute('reporting.dashboard', navigate: true);
    }

    // ── Daten ────────────────────────────────────────────────────

    #[Computed]
    public function feed(): ?VerbalizationFeed
    {
        return $this->feedId ? VerbalizationFeed::find($this->feedId) : null;
    }

    #[Computed]
    public function canEdit(): bool
    {
        $feed = $this->feed;
        if (! $feed) {
            return true;
        }

        return ($feed->team_id && $feed->team_id === $this->teamId())
            || ($feed->owner_user_id && $feed->owner_user_id === Auth::id());
    }

    #[Computed]
    public function catalog(): array
    {
        return app(SubjectCatalog::class)->all();
    }

    #[Computed]
    public function perspectives(): array
    {
        return app(ReportBuilder::class)->perspectives($this->teamId(), $this->draft['subject_type'] ?? null);
    }

    #[Computed]
    public function entityMatches(): array
    {
        return app(ReportScope::class)->searchEntities($this->teamId(), $this->entitySearch);
    }

    #[Computed]
    public function scopeLabel(): ?string
    {
        $scope = app(ReportScope::class);
        $mode = $this->draft['scope_mode'] ?? 'entity';
        $type = $this->draft['subject_type'] ?? null;

        if ($this->draft['scope_entity_id'] ?? null) {
            return $scope->entityName((int) $this->draft['scope_entity_id']);
        }
        if (in_array($mode, ['single', 'list'], true) && ! empty($this->draft['scope_ids']) && $type) {
            $catalog = app(SubjectCatalog::class);

            return collect($this->draft['scope_ids'])
                ->take(3)
                ->map(fn ($id) => $scope->subjectLabel($type, $id, $catalog))
                ->implode(', ') . (count($this->draft['scope_ids']) > 3 ? ' …' : '');
        }

        return null;
    }

    #[Computed]
    public function outputs()
    {
        if (! $this->feedId) {
            return collect();
        }

        return VerbalizationOutput::query()
            ->where('feed_id', $this->feedId)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function feedUrl(): ?string
    {
        return $this->feed ? rtrim((string) config('app.url'), '/') . '/feed/' . $this->feed->uuid . '.xml' : null;
    }

    protected function recount(): void
    {
        unset($this->scopeLabel);
        try {
            $this->scopeCount = app(ReportBuilder::class)->selector($this->draft) === null
                ? null
                : app(ReportBuilder::class)->subjects($this->draft, $this->teamId())->count();
        } catch (\Throwable $e) {
            $this->scopeCount = null;
        }
    }

    protected function visible(VerbalizationFeed $feed): bool
    {
        return $feed->team_id === null || $feed->team_id === $this->teamId();
    }

    protected function team()
    {
        return Auth::user()?->currentTeam;
    }

    protected function teamId(): ?int
    {
        return $this->team()?->id;
    }

    public function render()
    {
        return view('reporting::livewire.reports.builder')
            ->layout('platform::layouts.app');
    }
}
