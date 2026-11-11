{{-- Bericht-Baukasten — worüber · Ausschnitt · Perspektive · Rhythmus · Ausgabe, rechts die Probe --}}
@php
    $d = $draft;
    $catalog = $this->catalog;
    $type = $d['subject_type'] ?? null;
    $typeInfo = $type ? ($catalog[$type] ?? null) : null;
    $isEntityType = ($typeInfo['kind'] ?? null) === 'entity';
    $perspectives = $this->perspectives;
    $current = $perspectives[$d['recipe_key'] ?? ''] ?? null;
    $feed = $this->feed;
    $canEdit = $this->canEdit;

    $natureLabels = ['state' => 'Zustand', 'movement' => 'Bewegung', 'derivation' => 'Folgerungen'];
    $windowLabel = fn (?string $w) => match ($w) {
        null, '' => 'seit letztem Bericht',
        '1d' => 'letzter Tag', '7d', '1w' => 'letzte 7 Tage', '30d', '1m' => 'letzte 30 Tage',
        default => $w,
    };
    $rhythms = [
        'cron_weekly' => ['Wöchentlich', 'montags früh'],
        'cron_daily' => ['Täglich', 'jeden Morgen'],
        'on_request' => ['Auf Abruf', 'nur wenn du ihn anstößt'],
    ];

    $choice = 'w-full text-left rounded-[8px] border px-3 py-2.5 transition-colors';
    $choiceOn = 'border-[color:var(--nx-accent)] bg-[color:var(--nx-hover)]';
    $choiceOff = 'border-[color:var(--nx-line)] hover:bg-[color:var(--nx-hover)]';
    $step = 'inline-flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-semibold border border-[color:var(--nx-line-strong)] text-[color:var(--nx-muted)]';
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$feed ? $feed->title : 'Neuer Bericht'" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Reporting', 'icon' => 'document-chart-bar', 'route' => 'reporting.dashboard'],
            ['label' => $feed ? $feed->title : 'Neuer Bericht'],
        ]">
            @if($feed && $canEdit)
                <x-nx-dropdown label="Aktionen">
                    <x-nx-dropdown-item wire:click="refreshNow">Jetzt erzeugen</x-nx-dropdown-item>
                    @if($feed->access === 'public')
                        <x-nx-dropdown-item :href="$this->feedUrl" target="_blank">Feed öffnen</x-nx-dropdown-item>
                    @endif
                    <x-nx-dropdown-divider />
                    <x-nx-dropdown-item variant="danger" wire:click="delete"
                        wire:confirm="Diesen Bericht samt allen Ausgaben löschen?">Löschen</x-nx-dropdown-item>
                </x-nx-dropdown>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @unless($canEdit)
            <x-nx-callout variant="neutral" title="Nur lesen">
                Dieser Bericht gehört einem anderen Team. Du siehst ihn, kannst ihn aber nicht ändern.
            </x-nx-callout>
        @endunless

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">
            {{-- ═══ Links: der Baukasten ═══ --}}
            <div class="lg:col-span-3 space-y-8">

                {{-- Name --}}
                <div class="space-y-3">
                    <x-nx-input-text name="draft.title" label="Name des Berichts" wire:model.blur="draft.title"
                        :placeholder="$type ? app(\Platform\Reporting\Reports\ReportBuilder::class)->suggestTitle($d) : 'z. B. Wochenpuls Vertrieb'" />
                    <x-nx-input-textarea name="draft.description" label="Wofür ist er da?" wire:model.blur="draft.description" :rows="2"
                        placeholder="Optional — wer liest ihn, wozu?" />
                </div>

                {{-- 1 · Worüber --}}
                <x-nx-section title="Worüber wird berichtet?">
                    <x-slot name="action"><span class="{{ $step }}">1</span></x-slot>
                    @if(empty($catalog))
                        <x-nx-callout variant="warning" title="Keine Berichtsquellen">
                            Kein Modul hat einen Sammler registriert — es gibt noch nichts, worüber berichtet werden kann.
                        </x-nx-callout>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($catalog as $t => $info)
                                <button type="button" wire:click="chooseType('{{ $t }}')" @disabled(! $canEdit)
                                    class="{{ $choice }} {{ $type === $t ? $choiceOn : $choiceOff }}">
                                    <div class="flex items-center gap-2 text-sm font-medium text-[color:var(--nx-text)]">
                                        @svg($info['icon'], 'w-4 h-4 text-[color:var(--nx-muted)]')
                                        {{ $info['label'] }}
                                    </div>
                                    <p class="mt-1 text-xs text-[color:var(--nx-muted)] leading-relaxed">{{ $info['description'] }}</p>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </x-nx-section>

                {{-- 2 · Ausschnitt --}}
                @if($type)
                    <x-nx-section title="{{ $isEntityType ? 'Welche Einheit?' : 'Aus welchem Bereich?' }}"
                        description="{{ $isEntityType ? 'Der Bericht beschreibt genau diese Organisationseinheit.' : 'Alles, was an dieser Einheit hängt — auf Wunsch samt Untereinheiten.' }}">
                        <x-slot name="action"><span class="{{ $step }}">2</span></x-slot>

                        @if(! app(\Platform\Reporting\Reports\ReportScope::class)->organizationAvailable() && ($isEntityType || $d['scope_mode'] === 'entity'))
                            <x-nx-callout variant="warning" title="Organisation nicht installiert">
                                Ohne das Organisations-Modul lässt sich kein Ausschnitt wählen.
                            </x-nx-callout>
                        @elseif(in_array($d['scope_mode'], ['single', 'list'], true) && ! $isEntityType && ! empty($d['scope_ids']))
                            {{-- Bestehende Feeds mit fest gewählten Objekten --}}
                            <x-nx-card>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm">
                                        <span class="text-[color:var(--nx-muted)]">Fest gewählt:</span>
                                        <span class="font-medium">{{ $this->scopeLabel }}</span>
                                    </div>
                                    @if($canEdit)
                                        <x-nx-button variant="ghost" wire:click="clearEntity">Bereich wählen</x-nx-button>
                                    @endif
                                </div>
                            </x-nx-card>
                        @elseif($d['scope_entity_id'])
                            <x-nx-card>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        @svg('heroicon-o-building-office-2', 'w-4 h-4 text-[color:var(--nx-muted)]')
                                        {{ $this->scopeLabel ?? ('Einheit #' . $d['scope_entity_id']) }}
                                    </div>
                                    @if($canEdit)
                                        <x-nx-button variant="ghost" wire:click="clearEntity">Ändern</x-nx-button>
                                    @endif
                                </div>
                                @unless($isEntityType)
                                    <div class="mt-3 pt-3 border-t border-[color:var(--nx-line)] flex items-center justify-between gap-3">
                                        <x-nx-input-checkbox name="draft.descend" label="Untereinheiten einbeziehen" wire:model.live="draft.descend" :disabled="! $canEdit" />
                                        @if($scopeCount !== null)
                                            <span class="text-xs text-[color:var(--nx-muted)]">
                                                {{ $scopeCount }} {{ $scopeCount === 1 ? 'Eintrag' : 'Einträge' }} im Bericht
                                            </span>
                                        @endif
                                    </div>
                                @endunless
                            </x-nx-card>
                        @elseif($canEdit)
                            <x-nx-card flush>
                                <div class="p-3 border-b border-[color:var(--nx-line)]">
                                    <x-nx-input-text name="entitySearch" wire:model.live.debounce.250ms="entitySearch" placeholder="Einheit suchen …" size="sm" />
                                </div>
                                <ul class="divide-y divide-[color:var(--nx-line)] max-h-72 overflow-y-auto">
                                    @forelse($this->entityMatches as $e)
                                        <li>
                                            <button type="button" wire:click="chooseEntity({{ $e['id'] }})"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-[color:var(--nx-hover)] flex items-center justify-between gap-3">
                                                <span class="font-medium">{{ $e['name'] }}</span>
                                                @if($e['path'])
                                                    <span class="text-xs text-[color:var(--nx-faint)] truncate">in {{ $e['path'] }}</span>
                                                @endif
                                            </button>
                                        </li>
                                    @empty
                                        <li class="px-3 py-4 text-xs text-[color:var(--nx-faint)]">Keine Einheit gefunden.</li>
                                    @endforelse
                                </ul>
                            </x-nx-card>
                        @endif

                        @if($scopeCount === 0)
                            <p class="text-xs text-[color:var(--nx-warning)]">Im Ausschnitt liegt im Moment nichts — der Bericht bliebe leer.</p>
                        @endif
                    </x-nx-section>
                @endif

                {{-- 3 · Perspektive --}}
                @if($type)
                    <x-nx-section title="Aus welcher Perspektive?" description="Was erzählt wird und für wen — Zustand, Bewegung, Folgerungen.">
                        <x-slot name="action"><span class="{{ $step }}">3</span></x-slot>

                        @if(empty($perspectives))
                            <x-nx-callout variant="warning" title="Keine Perspektive">
                                Für {{ $typeInfo['label'] }} gibt es noch keine Perspektive (Recipe).
                            </x-nx-callout>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($perspectives as $key => $p)
                                    <button type="button" wire:click="choosePerspective('{{ $key }}')" @disabled(! $canEdit)
                                        class="{{ $choice }} {{ ($d['recipe_key'] ?? null) === $key ? $choiceOn : $choiceOff }}">
                                        <div class="text-sm font-medium text-[color:var(--nx-text)]">{{ $p['name'] }}</div>
                                        @if($p['description'])
                                            <p class="mt-1 text-xs text-[color:var(--nx-muted)] leading-relaxed">{{ $p['description'] }}</p>
                                        @endif
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach($p['natures'] as $n)
                                                <x-nx-badge>{{ $natureLabels[$n] ?? $n }}</x-nx-badge>
                                            @endforeach
                                            @if($p['since_window'])
                                                <x-nx-badge variant="info">{{ $windowLabel($p['since_window']) }}</x-nx-badge>
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                            @if($current)
                                <div class="pt-1">
                                    <button type="button" wire:click="toggleCustomize" @disabled(! $canEdit)
                                        class="inline-flex items-center gap-1.5 text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">
                                        @svg($d['customize'] ? 'heroicon-o-chevron-down' : 'heroicon-o-adjustments-horizontal', 'w-4 h-4')
                                        {{ $d['customize'] ? 'Anpassung aktiv — auf „' . $current['name'] . '" zurücksetzen' : 'Anpassen: Ton, Anrede, Länge, Inhalt' }}
                                    </button>
                                </div>

                                @if($d['customize'])
                                    <x-nx-card>
                                        <div class="space-y-4">
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                <x-nx-input-select name="draft.address" label="Anrede" wire:model.live="draft.address" size="sm"
                                                    :options="[['value' => 'sie', 'label' => 'Sie'], ['value' => 'du', 'label' => 'Du']]" />
                                                <x-nx-input-select name="draft.tone" label="Ton" wire:model.live="draft.tone" size="sm"
                                                    :options="[['value' => 'formal', 'label' => 'Sachlich'], ['value' => 'collegial', 'label' => 'Kollegial'], ['value' => 'evocative', 'label' => 'Erzählend']]" />
                                                <x-nx-input-select name="draft.rhythm" label="Länge" wire:model.live="draft.rhythm" size="sm"
                                                    :options="[['value' => 'short', 'label' => 'Knapp'], ['value' => 'flowing', 'label' => 'Ausführlich']]" />
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                                                <div>
                                                    <div class="mb-1 text-xs font-medium">Inhalt</div>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @foreach($natureLabels as $n => $label)
                                                            @php $on = in_array($n, (array) $d['natures'], true); @endphp
                                                            <button type="button" wire:click="toggleNature('{{ $n }}')"
                                                                class="rounded-full border px-2.5 py-1 text-xs transition-colors {{ $on ? 'border-[color:var(--nx-accent)] text-[color:var(--nx-text)] bg-[color:var(--nx-hover)]' : 'border-[color:var(--nx-line)] text-[color:var(--nx-faint)]' }}">
                                                                {{ $label }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <x-nx-input-select name="draft.since_window" label="Zeitraum für Bewegung" wire:model.live="draft.since_window" size="sm"
                                                    :options="[['value' => '', 'label' => 'Seit letztem Bericht'], ['value' => '1d', 'label' => 'Letzter Tag'], ['value' => '7d', 'label' => 'Letzte 7 Tage'], ['value' => '30d', 'label' => 'Letzte 30 Tage']]" />
                                            </div>
                                            <x-nx-input-textarea name="draft.extra_instruction" label="Eigene Anweisung" wire:model.blur="draft.extra_instruction" :rows="3"
                                                placeholder="z. B. Höchstens drei Sätze. Kein Fachjargon. Mit dem wichtigsten nächsten Schritt enden." />
                                        </div>
                                    </x-nx-card>
                                @endif
                            @endif
                        @endif
                    </x-nx-section>
                @endif

                {{-- 4 · Rhythmus --}}
                @if($type)
                    <x-nx-section title="Wie oft?">
                        <x-slot name="action"><span class="{{ $step }}">4</span></x-slot>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            @foreach($rhythms as $key => [$label, $hint])
                                <button type="button" wire:click="$set('draft.refresh_strategy', '{{ $key }}')" @disabled(! $canEdit)
                                    class="{{ $choice }} {{ $d['refresh_strategy'] === $key ? $choiceOn : $choiceOff }}">
                                    <div class="text-sm font-medium">{{ $label }}</div>
                                    <div class="text-xs text-[color:var(--nx-muted)]">{{ $hint }}</div>
                                </button>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-nx-input-select name="draft.item_strategy" label="Was bleibt stehen?" wire:model="draft.item_strategy" size="sm"
                                :options="[['value' => 'history', 'label' => 'Verlauf — jede Ausgabe bleibt'], ['value' => 'snapshot', 'label' => 'Nur der aktuelle Stand je Eintrag']]" />
                            <x-nx-input-number name="draft.retention_items" label="Höchstens aufbewahren" wire:model="draft.retention_items" size="sm" :min="1" :max="500" hint="Ausgaben" />
                        </div>
                        <p class="text-xs text-[color:var(--nx-faint)]">Hat sich seit der letzten Ausgabe nichts geändert, entsteht keine neue.</p>
                    </x-nx-section>
                @endif

                {{-- 5 · Ausgabe --}}
                @if($type)
                    <x-nx-section title="Wo erscheint er?">
                        <x-slot name="action"><span class="{{ $step }}">5</span></x-slot>
                        <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                            <x-nx-property-row icon="heroicon-o-building-office-2" label="In der Plattform">
                                <span class="text-sm text-[color:var(--nx-muted)]">Immer — hier und im Berichte-Tab der Einheit.</span>
                            </x-nx-property-row>
                            <x-nx-property-row icon="heroicon-o-rss" label="Feed-Link">
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-nx-input-checkbox name="feed_public" label="per Link abonnierbar (RSS/Atom)"
                                        :checked="$d['access'] === 'public'" :disabled="! $canEdit"
                                        wire:click="$set('draft.access', '{{ $d['access'] === 'public' ? 'team' : 'public' }}')" />
                                    @if($feed && $d['access'] === 'public')
                                        <x-nx-button variant="ghost" x-data
                                            x-on:click="navigator.clipboard.writeText('{{ $this->feedUrl }}'); $el.innerText = 'Kopiert'">Link kopieren</x-nx-button>
                                    @endif
                                </div>
                            </x-nx-property-row>
                            <x-nx-property-row icon="heroicon-o-envelope" label="Mail · PDF · Podcast">
                                <span class="text-sm text-[color:var(--nx-faint)]">Kommt als Nächstes.</span>
                            </x-nx-property-row>
                        </x-nx-card>
                    </x-nx-section>
                @endif

                {{-- Speichern --}}
                @if($canEdit)
                    <div class="flex flex-wrap items-center justify-end gap-3 pt-2 border-t border-[color:var(--nx-line)]">
                        @error('draft')
                            <span class="mr-auto text-xs text-[color:var(--nx-danger)]">{{ $message }}</span>
                        @enderror
                        @if($status)
                            <span class="mr-auto text-xs text-[color:var(--nx-success)]">{{ $status }}</span>
                        @endif
                        <x-nx-input-checkbox name="draft.is_active" label="Aktiv" wire:model="draft.is_active" />
                        <x-nx-button variant="primary" size="md" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                            {{ $feed ? 'Speichern' : 'Bericht anlegen' }}
                        </x-nx-button>
                    </div>
                @endif
            </div>

            {{-- ═══ Rechts: Probe & Ausgaben ═══ --}}
            <div class="lg:col-span-2 space-y-6 lg:sticky lg:top-4">
                <x-nx-section icon="heroicon-o-sparkles" title="Probe"
                    description="Ein echter Lauf mit dem aktuellen Entwurf — wird nicht gespeichert.">
                    <x-nx-card>
                        <div wire:loading.flex wire:target="generatePreview,nextPreview" class="items-center gap-2 py-8 justify-center text-xs text-[color:var(--nx-muted)]">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                            Sammle Fakten und schreibe …
                        </div>
                        <div wire:loading.remove wire:target="generatePreview,nextPreview">
                            @if($preview)
                                <div class="mb-3 flex items-center justify-between gap-2 text-xs text-[color:var(--nx-muted)]">
                                    <span class="truncate">
                                        <span class="font-medium text-[color:var(--nx-text)]">{{ $preview['label'] }}</span>
                                        @if($preview['total'] > 1) · {{ $preview['position'] }} von {{ $preview['total'] }} @endif
                                    </span>
                                    @if($preview['total'] > 1)
                                        <x-nx-button variant="ghost" wire:click="nextPreview">Nächster</x-nx-button>
                                    @endif
                                </div>
                                <x-nx-prose>{!! \Illuminate\Support\Str::markdown($preview['prose'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</x-nx-prose>
                                <div class="mt-3 pt-3 border-t border-[color:var(--nx-line)] flex items-center justify-between text-xs text-[color:var(--nx-faint)]">
                                    <span>{{ $preview['facts'] }} Fakten · {{ $preview['model'] }}</span>
                                    <x-nx-button variant="ghost" wire:click="generatePreview">Neu schreiben</x-nx-button>
                                </div>
                            @else
                                <x-nx-empty icon="heroicon-o-document-text">
                                    @if($previewError)
                                        <span class="text-[color:var(--nx-danger)]">{{ $previewError }}</span>
                                    @else
                                        So würde der Bericht gerade lauten.
                                    @endif
                                    <x-slot name="action">
                                        <x-nx-button variant="secondary" wire:click="generatePreview">Probe erzeugen</x-nx-button>
                                    </x-slot>
                                </x-nx-empty>
                            @endif
                        </div>
                    </x-nx-card>
                </x-nx-section>

                @if($feed)
                    <x-nx-section icon="heroicon-o-clock" title="Letzte Ausgaben"
                        :hint="$feed->last_refreshed_at ? 'zuletzt ' . $feed->last_refreshed_at->diffForHumans() : null">
                        @if($this->outputs->isEmpty())
                            <x-nx-card>
                                <x-nx-empty icon="heroicon-o-inbox">
                                    Noch keine Ausgabe.
                                    @if($canEdit)
                                        <x-slot name="action">
                                            <x-nx-button variant="secondary" wire:click="refreshNow">Jetzt erzeugen</x-nx-button>
                                        </x-slot>
                                    @endif
                                </x-nx-empty>
                            </x-nx-card>
                        @else
                            <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                                @foreach($this->outputs as $o)
                                    <details class="px-4 py-3" x-data="{ open: false }" x-on:toggle="open = $el.open">
                                        <summary class="cursor-pointer list-none flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium truncate">{{ $o->subject_label ?: $o->subject_type . ' #' . $o->subject_id }}</div>
                                                <div class="text-xs text-[color:var(--nx-muted)] line-clamp-2" x-show="! open">{{ \Illuminate\Support\Str::limit(strip_tags(\Illuminate\Support\Str::markdown($o->prose)), 180) }}</div>
                                            </div>
                                            <span class="shrink-0 text-xs text-[color:var(--nx-faint)]">{{ $o->created_at?->format('d.m. H:i') }}</span>
                                        </summary>
                                        <div class="mt-3">
                                            <x-nx-prose>{!! \Illuminate\Support\Str::markdown($o->prose, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</x-nx-prose>
                                        </div>
                                    </details>
                                @endforeach
                            </x-nx-card>
                        @endif
                    </x-nx-section>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
