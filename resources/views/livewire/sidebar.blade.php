{{-- Reporting — Modul-Sidebar (nx). Seiten: Berichte · Neuer Bericht; Werkstatt: Bausteine · Showcase. --}}
<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[color:var(--nx-line)] mb-2">
        Reporting
    </div>

    <x-ui-sidebar-list label="Berichte">
        <x-ui-sidebar-item :href="route('reporting.dashboard')" :active="request()->routeIs('reporting.dashboard', 'reporting.reports.edit')">
            @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Alle Berichte</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reporting.reports.create')" :active="request()->routeIs('reporting.reports.create')">
            @svg('heroicon-o-plus', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Neuer Bericht</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    <x-ui-sidebar-list label="Werkstatt">
        <x-ui-sidebar-item :href="route('reporting.factory')" :active="request()->routeIs('reporting.factory')">
            @svg('heroicon-o-wrench-screwdriver', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Bausteine</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reporting.showcase')" :active="request()->routeIs('reporting.showcase')">
            @svg('heroicon-o-swatch', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">nx-Showcase</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Eingeklappt: nur Icons --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[color:var(--nx-line)]">
        <div class="flex flex-col gap-2">
            @foreach([
                ['reporting.dashboard', 'heroicon-o-document-text'],
                ['reporting.reports.create', 'heroicon-o-plus'],
                ['reporting.factory', 'heroicon-o-wrench-screwdriver'],
            ] as [$r, $icon])
                <a href="{{ route($r) }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                    @svg($icon, 'w-5 h-5')
                </a>
            @endforeach
        </div>
    </div>
</div>
