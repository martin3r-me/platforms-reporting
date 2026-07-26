{{--
    Sidebar View
    Modul-spezifische Sidebar (nx-Design-System)

    WICHTIG FÜR LLMs:
    - Wird automatisch in der Haupt-Sidebar eingebunden
    - Verwendet x-ui-sidebar-list und x-ui-sidebar-item (Shell, „Eimer 1")
    - Unterstützt collapsed/expanded Zustand
    - Kann dynamische Listen enthalten

    DESIGN: Nur var(--nx-*) Tokens — Text=var(--nx-text), Linien=var(--nx-line),
    Hover=var(--nx-bg). KEINE var(--ui-*)/ENV-Farben.

    ANPASSUNGEN:
    - Füge modul-spezifische Navigation hinzu
    - Implementiere dynamische Listen (z.B. aus Datenbank)
--}}

<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[color:var(--nx-line)] mb-2">
        Module Template
    </div>

    {{-- Abschnitt: Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('reporting.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reporting.showcase')">
            @svg('heroicon-o-swatch', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">nx-Showcase</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[color:var(--nx-line)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('reporting.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('reporting.showcase') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-swatch', 'w-5 h-5')
            </a>
        </div>
    </div>

    {{-- BEISPIEL: Dynamische Liste (auskommentiert) --}}
    {{--
    <x-ui-sidebar-list label="Dynamische Liste">
        @foreach($entities as $entity)
            <x-ui-sidebar-item :href="route('reporting.entities.show', ['entity' => $entity])">
                @svg('heroicon-o-cube', 'w-4 h-4 text-[var(--nx-text)]')
                <span class="ml-2 text-sm">{{ $entity->name }}</span>
            </x-ui-sidebar-item>
        @endforeach
    </x-ui-sidebar-list>
    --}}
</div>
