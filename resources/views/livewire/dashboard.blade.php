{{-- Berichte — alle Berichte des Teams, je mit Ausschnitt, Rhythmus und Anfang der jüngsten Ausgabe --}}
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Berichte" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Reporting', 'icon' => 'document-chart-bar'],
        ]">
            <x-slot name="left">
                <x-nx-button variant="{{ $filter === 'active' ? 'secondary' : 'ghost' }}" size="sm" wire:click="setFilter('active')">Aktive</x-nx-button>
                <x-nx-button variant="{{ $filter === 'all' ? 'secondary' : 'ghost' }}" size="sm" wire:click="setFilter('all')">Alle</x-nx-button>
            </x-slot>

            <x-nx-button variant="primary" size="sm" :href="route('reporting.reports.create')" wire:navigate>
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Bericht</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @php $stats = $this->stats; $reports = $this->reports; @endphp

        <x-nx-stat-grid :cols="3">
            <x-nx-stat label="Aktive Berichte" :value="$stats['active']" icon="heroicon-o-document-text" />
            <x-nx-stat label="Laufen automatisch" :value="$stats['scheduled']" icon="heroicon-o-clock" hint="täglich / wöchentlich" />
            <x-nx-stat label="Ausgaben (30 Tage)" :value="$stats['outputs_30d']" icon="heroicon-o-sparkles" accent="var(--nx-success)" />
        </x-nx-stat-grid>

        @if(empty($reports))
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-document-chart-bar">
                    Noch kein Bericht. Stell dir einen zusammen — worüber, aus welchem Bereich, aus welcher Perspektive.
                    <x-slot name="action">
                        <x-nx-button variant="primary" :href="route('reporting.reports.create')" wire:navigate>Ersten Bericht bauen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            </x-nx-card>
        @else
            <x-nx-card flush>
                <ul class="divide-y divide-[color:var(--nx-line)]">
                    @foreach($reports as $r)
                        <li>
                            <a href="{{ route('reporting.reports.edit', ['feed' => $r['id']]) }}" wire:navigate
                               class="flex items-start gap-3 px-4 py-3 hover:bg-[color:var(--nx-hover)] transition-colors">
                                @svg($r['icon'], 'w-5 h-5 mt-0.5 shrink-0 text-[color:var(--nx-faint)]')
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $r['title'] }}</span>
                                        @unless($r['active'])
                                            <x-nx-badge>pausiert</x-nx-badge>
                                        @endunless
                                        @if($r['global'])
                                            <x-nx-badge variant="info">global</x-nx-badge>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-[color:var(--nx-muted)]">{{ $r['summary'] }}</div>
                                    @if($r['excerpt'])
                                        <p class="mt-1.5 text-xs text-[color:var(--nx-faint)] line-clamp-2">{{ $r['excerpt'] }}</p>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right text-xs text-[color:var(--nx-faint)]">
                                    <div>{{ $r['last'] ?? 'noch nie' }}</div>
                                    <div>{{ $r['outputs'] }} {{ $r['outputs'] === 1 ? 'Ausgabe' : 'Ausgaben' }}</div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-nx-card>
        @endif
    </x-ui-page-container>
</x-ui-page>
