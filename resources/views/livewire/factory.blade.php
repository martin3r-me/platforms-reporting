<div class="p-6 max-w-7xl mx-auto">
    @php
        $badgeOn = 'inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-medium';
        $badgeMuted = 'inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-[var(--ui-surface-2)] text-[var(--ui-muted)]';
        $badgeGhost = 'inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full border border-dashed border-[var(--ui-border)] text-[var(--ui-muted)]/60';
        $badgeWarn = 'inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800';
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[var(--ui-secondary)]">Verbalizer — Baukasten</h1>
        <p class="text-sm text-[var(--ui-muted)] mt-1 max-w-3xl leading-relaxed">
            Ueberblick ueber alle registrierten Bausteine: welche Subject-Types werden bedient, welche Recipes existieren, welche EntityLinkProvider liefern welche Metriken, welche Kanal-Renderer sind verfuegbar. Rein Diagnose — Konfiguration weiter ueber MCP-Tools.
        </p>
    </div>

    <div class="flex gap-2 mb-6 border-b border-[var(--ui-border)]">
        @foreach([
            'subjects' => 'Subject-Types',
            'recipes' => 'Recipes',
            'providers' => 'Metrik-Provider',
            'channels' => 'Kanal-Renderer',
        ] as $key => $label)
            <button
                wire:click="setSection('{{ $key }}')"
                class="px-4 py-2.5 text-sm transition-colors {{ $activeSection === $key ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold' : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Subject-Types --}}
    @if($activeSection === 'subjects')
        @php $items = $this->subjectTypes; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($items as $item)
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-5">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="text-base font-semibold text-[var(--ui-secondary)]">{{ $item['type'] }}</h3>
                        <div class="flex flex-wrap gap-1">
                            @if($item['collector_class'])
                                <span class="{{ $badgeOn }}">Collector</span>
                            @else
                                <span class="{{ $badgeWarn }}">Collector fehlt</span>
                            @endif
                            @if($item['template_registered'])
                                <span class="{{ $badgeOn }}">Template</span>
                            @else
                                <span class="{{ $badgeWarn }}">Template fehlt</span>
                            @endif
                        </div>
                    </div>
                    @if($item['collector_class'])
                        <p class="text-xs text-[var(--ui-muted)] font-mono mb-2 break-all">{{ $item['collector_class'] }}</p>
                    @endif
                    @if(is_array($item['sources']))
                        <div class="mt-3">
                            <p class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)] mb-1.5">DEFAULT_SOURCES</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($item['sources'] as $s)
                                    <span class="{{ $badgeMuted }}">{{ $s }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="mt-3 text-xs text-[var(--ui-muted)]">
                        Outputs 30d: <span class="font-medium text-[var(--ui-secondary)]">{{ $item['outputs_30d'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Recipes --}}
    @if($activeSection === 'recipes')
        @php $bySubject = $this->recipesBySubject; @endphp
        <div class="space-y-6">
            @foreach($bySubject as $subjectType => $recipes)
                <div>
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-2 flex items-center gap-2">
                        {{ $subjectType }}
                        <span class="text-[10px] font-normal text-[var(--ui-muted)]">{{ count($recipes) }} Recipes</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($recipes as $r)
                            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $r['key'] }}</span>
                                            @if(! $r['is_active'])
                                                <span class="{{ $badgeMuted }}">inaktiv</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $r['name'] }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <span class="{{ $badgeMuted }}">{{ $r['scope'] }}</span>
                                    @if($r['llm_model'])
                                        <span class="{{ $badgeMuted }}">{{ $r['llm_provider'] }}/{{ $r['llm_model'] }}</span>
                                    @endif
                                    @if($r['descend'])
                                        <span class="{{ $badgeOn }}">Sub-Baum</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @php
                                        $natures = is_array($r['include_natures']) ? $r['include_natures'] : ['state','movement','derivation'];
                                    @endphp
                                    @foreach(['state','movement','derivation'] as $n)
                                        @if(in_array($n, $natures))
                                            <span class="{{ $badgeOn }}">{{ $n }}</span>
                                        @else
                                            <span class="{{ $badgeGhost }}">{{ $n }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- EntityLinkProvider --}}
    @if($activeSection === 'providers')
        @php $providers = $this->entityLinkProviders; @endphp
        <div class="bg-white rounded-lg border border-[var(--ui-border)] overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-[var(--ui-surface-2)] text-left text-[10px] uppercase tracking-wide text-[var(--ui-muted)]">
                        <th class="px-3 py-2">Modul</th>
                        <th class="px-3 py-2">Aliase</th>
                        <th class="px-3 py-2">Metrik-Defs</th>
                        <th class="px-3 py-2">metrics()</th>
                        <th class="px-3 py-2">Dimensionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($providers as $p)
                        <tr class="border-t border-[var(--ui-border)]">
                            <td class="px-3 py-2 font-medium text-[var(--ui-secondary)]">{{ $p['module'] }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($p['aliases'] as $a)
                                        <span class="{{ $badgeMuted }}">{{ $a }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                @if($p['has_metric_defs'])
                                    <span class="{{ $badgeOn }}">{{ $p['metrics_count'] }} Keys</span>
                                @else
                                    <span class="{{ $badgeWarn }}">keine Defs</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($p['metrics_callable'])
                                    <span class="{{ $badgeOn }}">✓</span>
                                @else
                                    <span class="{{ $badgeWarn }}">?</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($p['dimensions'] as $d)
                                        <span class="{{ $badgeMuted }}">{{ $d }}</span>
                                    @endforeach
                                    @if(empty($p['dimensions']))
                                        <span class="text-xs text-[var(--ui-muted)] italic">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Kanal-Renderer --}}
    @if($activeSection === 'channels')
        @php $channels = $this->channelRenderers; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($channels as $c)
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="text-base font-semibold text-[var(--ui-secondary)]">{{ $c['type'] }}</h3>
                        @if($c['registered'])
                            <span class="{{ $c['is_push'] ? $badgeOn : $badgeMuted }}">{{ $c['is_push'] ? 'Push' : 'Pull' }}</span>
                        @else
                            <span class="{{ $badgeGhost }}">nicht registriert</span>
                        @endif
                    </div>
                    @if($c['registered'])
                        <p class="text-xs text-[var(--ui-muted)] font-mono mb-2 break-all">{{ $c['class'] }}</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <span class="{{ $badgeMuted }}">{{ $c['content_type'] }}</span>
                            <span class="{{ $c['active_count'] > 0 ? $badgeOn : $badgeGhost }}">
                                {{ $c['active_count'] }} aktive Kanaele
                            </span>
                        </div>
                    @else
                        <p class="text-xs text-[var(--ui-muted)] italic">Renderer noch nicht implementiert — Ausbau moeglich.</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
