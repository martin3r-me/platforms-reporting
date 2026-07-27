{{--
    nx-Showcase — lebendes Verzeichnis ALLER x-nx-* Komponenten.

    Zweck: eine Seite, auf der jede Komponente einmal echt gerendert wird — als
    visuelle Referenz, Copy-Paste-Quelle und Frühwarnung (bricht etwas oder fehlt
    eine Tailwind-Klasse nach `npm run build`, sieht man es hier sofort).

    Pflege: Neue nx-Komponente? → hier einen <x-nx-section>-Block ergänzen.
    Alle Beispieldaten sind inline (DB-frei). Nur var(--nx-*) Tokens.
--}}

@php
    // Inline-Beispieldaten (keine Models nötig)
    $people = [
        ['name' => 'Anna Berg',    'role' => 'Design'],
        ['name' => 'Ben Cordes',   'role' => 'Backend'],
        ['name' => 'Cem Dogan',    'role' => 'Produkt'],
    ];
    $rows = [
        ['name' => 'Rechnung 2024-118', 'date' => '12.03.2026', 'badge' => 'success', 'label' => 'Bezahlt'],
        ['name' => 'Rechnung 2024-119', 'date' => '14.03.2026', 'badge' => 'warning', 'label' => 'Offen'],
        ['name' => 'Rechnung 2024-120', 'date' => '15.03.2026', 'badge' => 'danger',  'label' => 'Storniert'],
    ];
    $items = [
        ['icon' => 'heroicon-o-cube',        'title' => 'Projekt Alpha', 'subtitle' => 'Interne Tools',    'meta' => 'vor 2 Std.'],
        ['icon' => 'heroicon-o-sparkles',    'title' => 'Projekt Beta',  'subtitle' => 'Kundenportal',     'meta' => 'gestern'],
        ['icon' => 'heroicon-o-rocket-launch','title' => 'Projekt Gamma', 'subtitle' => 'Marketing-Site',  'meta' => 'vor 3 Tagen'],
    ];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Reporting" />
    </x-slot>

    {{-- Actionbar: ≥2 Aktionen → EIN Dropdown (Konvention) --}}
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Reporting', 'href' => route('reporting.dashboard'), 'icon' => 'cube'],
            ['label' => 'nx-Showcase'],
        ]">
            <x-nx-dropdown label="Aktionen">
                <x-nx-dropdown-item :href="route('reporting.dashboard')">
                    @svg('heroicon-o-home', 'w-4 h-4') Zum Dashboard
                </x-nx-dropdown-item>
                <x-nx-dropdown-item wire:click="$refresh">
                    @svg('heroicon-o-arrow-path', 'w-4 h-4') Neu laden
                </x-nx-dropdown-item>
                <x-nx-dropdown-divider />
                <x-nx-dropdown-item variant="danger" wire:click="$refresh">
                    @svg('heroicon-o-trash', 'w-4 h-4') Beispiel-Aktion
                </x-nx-dropdown-item>
            </x-nx-dropdown>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-10">

        <x-nx-callout variant="info" icon="heroicon-o-information-circle" title="Lebendes Beispiel">
            Jede <code>x-nx-*</code> Komponente einmal gerendert. Der Quelltext dieser View
            (<code>resources/views/livewire/showcase.blade.php</code>) ist zugleich die Copy-Paste-Referenz.
            Volle Prop-Liste je Komponente im Docblock unter <code>ui-tailwind/.../components-nx/</code>.
        </x-nx-callout>

        {{-- ═══════════════ Buttons ═══════════════ --}}
        <x-nx-section icon="heroicon-o-cursor-arrow-rays" title="Button"
                      description="variant: secondary (default) / primary / ghost / danger · size sm|md · icon · href · disabled">
            <x-nx-card>
                <div class="flex flex-wrap items-center gap-3">
                    <x-nx-button variant="primary">Primary</x-nx-button>
                    <x-nx-button variant="secondary">Secondary</x-nx-button>
                    <x-nx-button variant="ghost">Ghost</x-nx-button>
                    <x-nx-button variant="danger">Danger</x-nx-button>
                    <x-nx-button variant="primary" size="md">Größe md</x-nx-button>
                    <x-nx-button variant="secondary" icon>@svg('heroicon-o-pencil', 'w-4 h-4')</x-nx-button>
                    <x-nx-button variant="secondary" :href="route('reporting.dashboard')">Als Link</x-nx-button>
                    <x-nx-button variant="primary" disabled>Disabled</x-nx-button>
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Badge ═══════════════ --}}
        <x-nx-section icon="heroicon-o-tag" title="Badge"
                      description="Status-Pille. variant neutral/success/danger/warning/info/accent · dot">
            <x-nx-card>
                <div class="flex flex-wrap items-center gap-2">
                    <x-nx-badge>Neutral</x-nx-badge>
                    <x-nx-badge variant="success" dot>Aktiv</x-nx-badge>
                    <x-nx-badge variant="warning" dot>Offen</x-nx-badge>
                    <x-nx-badge variant="danger" dot>Fehler</x-nx-badge>
                    <x-nx-badge variant="info">Info</x-nx-badge>
                    <x-nx-badge variant="accent">Akzent</x-nx-badge>
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Callout ═══════════════ --}}
        <x-nx-section icon="heroicon-o-megaphone" title="Callout"
                      description="Hinweis/Attention mit bedeutungstragender Farbe. variant info/success/warning/danger/neutral">
            <div class="space-y-3">
                <x-nx-callout variant="info" title="Info">Neutrale Information zum Kontext.</x-nx-callout>
                <x-nx-callout variant="success" title="Erledigt">Alles synchronisiert.</x-nx-callout>
                <x-nx-callout variant="warning" title="3 Buchungen offen">
                    warten auf Bestätigung
                    <x-slot name="action"><x-nx-button variant="ghost" size="sm">Ansehen</x-nx-button></x-slot>
                </x-nx-callout>
                <x-nx-callout variant="danger" title="Fehlgeschlagen">Import konnte nicht abgeschlossen werden.</x-nx-callout>
            </div>
        </x-nx-section>

        {{-- ═══════════════ Card ═══════════════ --}}
        <x-nx-section icon="heroicon-o-square-2-stack" title="Card"
                      description="Grundfläche (surface + Hairline). flush = ohne Padding · hover = klickbare Fläche">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <x-nx-card>
                    <div class="text-sm font-medium text-[color:var(--nx-text)]">Standard</div>
                    <p class="text-sm text-[color:var(--nx-muted)] mt-1">Mit Padding.</p>
                </x-nx-card>
                <x-nx-card hover>
                    <div class="text-sm font-medium text-[color:var(--nx-text)]">Hover</div>
                    <p class="text-sm text-[color:var(--nx-muted)] mt-1">Dezente Hover-Fläche.</p>
                </x-nx-card>
                <x-nx-card flush>
                    <div class="p-4">
                        <div class="text-sm font-medium text-[color:var(--nx-text)]">Flush</div>
                        <p class="text-sm text-[color:var(--nx-muted)] mt-1">Kein Padding — für Tabellen/Listen.</p>
                    </div>
                </x-nx-card>
            </div>
        </x-nx-section>

        {{-- ═══════════════ Stat ═══════════════ --}}
        <x-nx-section icon="heroicon-o-chart-bar" title="Stat / Stat-Grid"
                      description="Kennzahlen. KPI-Icon trägt Semantikfarbe (accent), sonst neutral. Grid: cols">
            <x-nx-stat-grid :cols="4">
                <x-nx-stat label="Gesamt" value="128" icon="heroicon-o-cube" />
                <x-nx-stat label="Aktiv"  value="97"  icon="heroicon-o-check-circle" accent="var(--nx-success)" />
                <x-nx-stat label="Offen"  value="18"  icon="heroicon-o-inbox" accent="var(--nx-warning)" hint="warten" />
                <x-nx-stat label="Fehler" value="3"   icon="heroicon-o-x-circle" accent="var(--nx-danger)" />
            </x-nx-stat-grid>
        </x-nx-section>

        {{-- ═══════════════ Table ═══════════════ --}}
        <x-nx-section icon="heroicon-o-table-cells" title="Table"
                      description="Register/Ledger: gleichförmige Datensätze, spaltenweise vergleichen/sortieren. In x-nx-card flush.">
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Beleg</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Datum</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($rows as $row)
                            <x-nx-table-row>
                                <x-nx-table-cell>{{ $row['name'] }}</x-nx-table-cell>
                                <x-nx-table-cell align="right">{{ $row['date'] }}</x-nx-table-cell>
                                <x-nx-table-cell>
                                    <x-nx-badge :variant="$row['badge']" dot>{{ $row['label'] }}</x-nx-badge>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ List-Item ═══════════════ --}}
        <x-nx-section icon="heroicon-o-list-bullet" title="List-Item"
                      description="Katalog/Entitäten mit Identität (Icon, Titel, Badges). Zeile klickbar = Hauptaktion. In x-nx-card flush + divide-y.">
            <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                @foreach($items as $it)
                    <x-nx-list-item :icon="$it['icon']" :title="$it['title']"
                                    :subtitle="$it['subtitle']" :meta="$it['meta']"
                                    :href="route('reporting.showcase')" />
                @endforeach
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Empty ═══════════════ --}}
        <x-nx-section icon="heroicon-o-inbox" title="Empty"
                      description="Ruhiger Leerzustand (Icon + Text, optional Aktion).">
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-inbox">
                    Noch keine Einträge vorhanden
                    <x-slot name="action"><x-nx-button variant="primary" size="sm">Anlegen</x-nx-button></x-slot>
                </x-nx-empty>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Tabs ═══════════════ --}}
        <x-nx-section icon="heroicon-o-rectangle-group" title="Tabs"
                      description="Ruhige Unterstrich-Navigation. active-Prop je Reiter; Umschalten via wire:click.">
            <x-nx-card>
                <x-nx-tabs>
                    <x-nx-tab :active="$tab === 'eins'" wire:click="$set('tab', 'eins')">Übersicht</x-nx-tab>
                    <x-nx-tab :active="$tab === 'zwei'" wire:click="$set('tab', 'zwei')">Details</x-nx-tab>
                    <x-nx-tab :active="$tab === 'drei'" wire:click="$set('tab', 'drei')">Verlauf</x-nx-tab>
                </x-nx-tabs>
                <div class="text-sm text-[color:var(--nx-muted)]">
                    @if($tab === 'eins') Inhalt des Reiters <strong>Übersicht</strong>.
                    @elseif($tab === 'zwei') Inhalt des Reiters <strong>Details</strong>.
                    @else Inhalt des Reiters <strong>Verlauf</strong>.
                    @endif
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Dropdown ═══════════════ --}}
        <x-nx-section icon="heroicon-o-ellipsis-horizontal" title="Dropdown"
                      description="Aktionsmenü. Ohne label = Kebab (⋯). item: href / variant danger / disabled. Bei ≥2 Aktionen in der Actionbar.">
            <x-nx-card>
                <div class="flex items-center gap-4">
                    <x-nx-dropdown label="Mit Label">
                        <x-nx-dropdown-item>@svg('heroicon-o-eye', 'w-4 h-4') Öffnen</x-nx-dropdown-item>
                        <x-nx-dropdown-item>@svg('heroicon-o-pencil', 'w-4 h-4') Bearbeiten</x-nx-dropdown-item>
                        <x-nx-dropdown-divider />
                        <x-nx-dropdown-item variant="danger">@svg('heroicon-o-trash', 'w-4 h-4') Löschen</x-nx-dropdown-item>
                    </x-nx-dropdown>
                    <x-nx-dropdown align="start">
                        <x-nx-dropdown-item>Kebab-Trigger (⋯)</x-nx-dropdown-item>
                        <x-nx-dropdown-item disabled>Deaktiviert</x-nx-dropdown-item>
                    </x-nx-dropdown>
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Avatar ═══════════════ --}}
        <x-nx-section icon="heroicon-o-user-circle" title="Avatar"
                      description="Abgerundetes Quadrat (kein Vollkreis). size sm/md/lg · status online · ring zum Stapeln.">
            <x-nx-card>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <x-nx-avatar name="Anna Berg" size="sm" />
                        <x-nx-avatar name="Ben Cordes" size="md" status="online" />
                        <x-nx-avatar name="Cem Dogan" size="lg" />
                    </div>
                    {{-- gestapelt --}}
                    <div class="flex -space-x-2">
                        @foreach($people as $p)
                            <x-nx-avatar :name="$p['name']" ring />
                        @endforeach
                    </div>
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Property-Row ═══════════════ --}}
        <x-nx-section icon="heroicon-o-bars-3-bottom-left" title="Property-Row"
                      description="Notion-Property-Zeile: Icon + Label (fix) + Wert/Editor (flex). Für Detail-Seiten.">
            <x-nx-card>
                <div class="space-y-1">
                    <x-nx-property-row icon="heroicon-o-user" label="Verantwortlich">
                        <span class="text-sm text-[color:var(--nx-text)]">Anna Berg</span>
                    </x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-flag" label="Status">
                        <x-nx-badge variant="success" dot>Aktiv</x-nx-badge>
                    </x-nx-property-row>
                    <x-nx-property-row icon="heroicon-o-calendar" label="Fällig">
                        <span class="text-sm text-[color:var(--nx-muted)]">31.12.2026</span>
                    </x-nx-property-row>
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Formularfelder ═══════════════ --}}
        <x-nx-section icon="heroicon-o-pencil-square" title="Formularfelder"
                      description="input-text/-number/-date/-datetime/-textarea/-select/-checkbox. name/label/hint/required/errorKey; wire:model via Attribute.">
            <x-nx-card>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-nx-input-text name="demoText" label="Text" wire:model="demoText" placeholder="z.B. Projektname" required />
                    <x-nx-input-number name="demoNumber" label="Zahl" wire:model="demoNumber" :min="0" :max="100" placeholder="0" />
                    <x-nx-input-date name="demoDate" label="Datum" wire:model="demoDate" />
                    <x-nx-input-datetime name="demoDatetime" label="Datum & Zeit" wire:model="demoDatetime" />
                    <x-nx-input-select name="demoSelect" label="Auswahl" wire:model="demoSelect"
                        nullable nullLabel="– bitte wählen –"
                        :options="[
                            ['value' => 'a', 'label' => 'Option A'],
                            ['value' => 'b', 'label' => 'Option B'],
                            ['value' => 'c', 'label' => 'Option C'],
                        ]" />
                    <div class="md:col-span-2">
                        <x-nx-input-textarea name="demoTextarea" label="Notiz" wire:model="demoTextarea" :rows="3" hint="Optionaler Freitext." />
                    </div>
                    <x-nx-input-checkbox label="Verfügbar" wire:model="demoCheckbox" />
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Modal ═══════════════ --}}
        <x-nx-section icon="heroicon-o-window" title="Modal"
                      description="Dialog, wächst mit Inhalt (max-h 85vh). size sm (Bestätigung) / md (Default) / lg (mehrspaltig) / xl (Tools).">
            <x-nx-card>
                <div class="flex flex-wrap gap-3">
                    <x-nx-button variant="secondary" wire:click="$set('showModalSm', true)">Modal sm</x-nx-button>
                    <x-nx-button variant="primary" wire:click="$set('showModalMd', true)">Modal md</x-nx-button>
                    <x-nx-button variant="secondary" wire:click="$set('showModalLg', true)">Modal lg</x-nx-button>
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- ═══════════════ Kanban ═══════════════ --}}
        <x-nx-section icon="heroicon-o-view-columns" title="Kanban"
                      description="Board: container + column (tone-Wash + count) + card. Hier rein visuell (ohne Drag). Container ist h-full → braucht Höhe; echte Boards laufen auf width=&quot;full&quot;.">
            {{-- fester Höhen-Wrapper, da der Container h-full ist (auf echten Boards trägt das Flex-Layout die Höhe) --}}
            <div class="h-80 rounded-[8px] border border-[color:var(--nx-line)] overflow-hidden">
                <x-nx-kanban-container>
                    <x-nx-kanban-column title="Backlog" tone="slate" :count="2" muted>
                        <x-nx-kanban-card title="Recherche Wettbewerb" />
                        <x-nx-kanban-card title="Interviews auswerten" />
                    </x-nx-kanban-column>
                    <x-nx-kanban-column title="In Arbeit" tone="sky" :count="2">
                        <x-nx-kanban-card title="Onboarding-Flow">
                            <x-slot name="footer"><span class="text-xs text-[color:var(--nx-faint)]">Anna · heute</span></x-slot>
                        </x-nx-kanban-card>
                        <x-nx-kanban-card title="API-Anbindung" />
                    </x-nx-kanban-column>
                    <x-nx-kanban-column title="Erledigt" tone="emerald" :count="1" muted>
                        <x-nx-kanban-card title="Setup Repository" />
                    </x-nx-kanban-column>
                </x-nx-kanban-container>
            </div>
        </x-nx-section>

        {{-- ═══════════════ Bauhaus ═══════════════ --}}
        <x-nx-section icon="heroicon-o-swatch" title="Bauhaus"
                      description="Dekorative generative Grafik (gedämpfte Palette). seed = stabil bei Re-Render · count = Formanzahl. Füllt das relative Elternelement.">
            <x-nx-card flush>
                <div class="relative h-40 overflow-hidden rounded-[8px]">
                    <x-nx-bauhaus :seed="42" :count="7" />
                </div>
            </x-nx-card>
        </x-nx-section>

    </x-ui-page-container>

    {{-- Linke Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Komponenten" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">nx-Showcase</h3>
                    <p class="text-sm text-[color:var(--nx-muted)]">
                        Jede x-nx-* Komponente einmal live. Quelltext dieser View = Copy-Paste-Referenz.
                    </p>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar (Activity) — auf-/zuklappbar, storeKey "activityOpen", side="right" --}}
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

    {{-- Modals — IMMER innerhalb von x-ui-page --}}
    <x-nx-modal size="sm" wire:model="showModalSm">
        <x-slot name="header">Bestätigen</x-slot>
        <p class="text-sm text-[color:var(--nx-muted)]">Eine kurze Bestätigung (size sm).</p>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showModalSm', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="$set('showModalSm', false)">OK</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    <x-nx-modal size="md" wire:model="showModalMd">
        <x-slot name="header">Eintrag anlegen</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="m_name" label="Name" placeholder="z.B. Projekt Delta" required />
            <x-nx-input-textarea name="m_note" label="Beschreibung" :rows="3" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showModalMd', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="$set('showModalMd', false)">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    <x-nx-modal size="lg" wire:model="showModalLg">
        <x-slot name="header">Mehrspaltiger Inhalt</x-slot>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-nx-input-text name="l_first" label="Vorname" />
            <x-nx-input-text name="l_last" label="Nachname" />
            <x-nx-input-date name="l_date" label="Datum" />
            <x-nx-input-select name="l_role" label="Rolle" :options="[
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'user',  'label' => 'Benutzer'],
            ]" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showModalLg', false)">Schließen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
