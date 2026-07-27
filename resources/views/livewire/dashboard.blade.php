{{--
    Dashboard View — leeres, aber voll funktionsfähiges Grundgerüst (nx-Design-System)

    Zeigt die Standard-Page-Shell eines Moduls:
    - Navbar + Actionbar (Breadcrumbs + genau 1 primäre Aktion)
    - Content-Bereich (x-ui-page-container)
    - Linke Sidebar  (sidebar-Slot,  storeKey "sidebarOpen")
    - Rechte Sidebar (activity-Slot, storeKey "activityOpen", side="right")

    ─────────────────────────────────────────────────────────────────────────
    DESIGN: Dieses Modul nutzt das plattformweite nx-Design-System (Notion-Look).
    - Rahmen/Shell bleiben x-ui-* (bereits auf nx-Tokens, „Eimer 1").
    - Inhalt baut ausschließlich mit x-nx-* Komponenten + var(--nx-*) Tokens.
    - KEINE var(--ui-*)/ENV-Farben, keine Gradients, keine Schatten (außer Popover/Modal).
    - Kanonische Referenz: ui-tailwind/docs/nx-design-system.md und dieses Modul.
    ─────────────────────────────────────────────────────────────────────────

    Der Content startet bewusst LEER (x-nx-empty). Ersetze ihn durch deine Inhalte.
    Unten steht ein auskommentierter Referenzblock mit den nx-Bausteinen zum Kopieren.
--}}

<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="Reporting" />
    </x-slot>

    {{--
        Actionbar = Seitenkopf-Navigation.
        Links: Breadcrumb. Rechts (default-Slot): Aktionen.
        Konvention: genau 1 Aktion → sichtbarer x-nx-button; ≥2 → ein x-nx-dropdown.
    --}}
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Reporting', 'icon' => 'cube'],
        ]">
            <x-nx-button variant="primary" size="sm" wire:click="$refresh">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neu</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    {{--
        Hauptinhalt. width="contained" (max-w 1200, linksbündig) für
        Dashboards/Listen/Formulare; "full" für Kanban/Canvas/breite Tabellen.
    --}}
    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-cube">
                Leeres Modul-Grundgerüst mit funktionsfähiger Page-Shell.
                Diesen Content-Bereich durch deine Inhalte ersetzen.
                <x-slot name="action">
                    <x-nx-button variant="secondary" size="sm" wire:click="$refresh">
                        Loslegen
                    </x-nx-button>
                </x-slot>
            </x-nx-empty>
        </x-nx-card>

        {{--
            ══════════════════════════════════════════════════════════════════
            nx-BAUSTEIN-REFERENZ (auskommentiert) — kopieren & anpassen.
            Volle Prop-Liste je Komponente: Docblock in
            ui-tailwind/resources/views/components-nx/<name>.blade.php.
            WICHTIG: Blade-Kommentare NICHT verschachteln — dieser Block ist EIN
            Kommentar; innere Labels stehen daher als Klartext-Zeilen (>> …).
            ══════════════════════════════════════════════════════════════════

            >> Kennzahlen: KPI-Icon trägt Semantikfarbe, sonst neutral
            <x-nx-stat-grid :cols="4">
                <x-nx-stat label="Gesamt"   :value="$stats['total']"  icon="heroicon-o-cube" />
                <x-nx-stat label="Aktiv"    :value="$stats['active']" icon="heroicon-o-check-circle" accent="var(--nx-success)" />
                <x-nx-stat label="Offen"    :value="$stats['open']"   icon="heroicon-o-inbox" accent="var(--nx-warning)" hint="warten" />
                <x-nx-stat label="Fehler"   :value="$stats['error']"  icon="heroicon-o-x-circle" accent="var(--nx-danger)" />
            </x-nx-stat-grid>

            >> Section = ruhiger Kopf über einem Container
            <x-nx-section icon="heroicon-o-list-bullet" title="Einträge" :hint="$entities->count()"
                          description="Register-artige Daten → Tabelle; Entitäten mit Identität → Liste">
                <x-slot name="action">
                    <x-nx-button variant="ghost" size="sm" :href="route('reporting.entities.index')">Alle</x-nx-button>
                </x-slot>

                >> TABELLE = Register/Ledger: gleichförmige Datensätze, spaltenweise vergleichen/sortieren
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Datum</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($entities as $entity)
                                <x-nx-table-row :href="route('reporting.entities.show', $entity)">
                                    <x-nx-table-cell>{{ $entity->name }}</x-nx-table-cell>
                                    <x-nx-table-cell align="right">{{ $entity->created_at->format('d.m.Y') }}</x-nx-table-cell>
                                    <x-nx-table-cell><x-nx-badge variant="success" dot>Aktiv</x-nx-badge></x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>

                >> LISTE = Katalog/Entitäten: Element mit Identität, erkennen & handeln statt vergleichen
                <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                    @foreach($entities as $entity)
                        <x-nx-list-item :href="route('reporting.entities.show', $entity)"
                                        icon="heroicon-o-cube"
                                        :title="$entity->name"
                                        subtitle="Beschreibung"
                                        :meta="$entity->created_at->diffForHumans()" />
                    @endforeach
                </x-nx-card>
            </x-nx-section>

            >> Hinweis/Attention → Callout (bedeutungstragende Farbe)
            <x-nx-callout variant="info" icon="heroicon-o-information-circle" title="Hinweis">
                Farbe lebt nur bedeutungstragend im Inhalt — Status, Hinweis, KPI, Timing.
            </x-nx-callout>
        --}}
    </x-ui-page-container>

    {{-- Linke Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Übersicht</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">Noch keine Einträge.</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Letzte Aktivitäten</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">Keine Aktivitäten verfügbar.</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{--
        Modals IMMER innerhalb von <x-ui-page> platzieren (sonst „Multiple Root Elements").
        <x-nx-modal wire:model="showCreate" size="md">
            <x-slot name="header">Eintrag anlegen</x-slot>
            <div class="space-y-4">
                <x-nx-input-text name="form.name" label="Name" wire:model="form.name" required />
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
                </div>
            </x-slot>
        </x-nx-modal>
    --}}
</x-ui-page>
