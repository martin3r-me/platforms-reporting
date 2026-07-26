<?php

namespace Platform\Reporting\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationRecipe;

/**
 * core.verbalization.recipes.POST
 *
 * Erstellt eine neue Verbalization-Recipe (Sammel-+Verbalisierungs-Auftrag).
 */
class CreateRecipeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.verbalization.recipes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /verbalization/recipes - Legt eine Verbalization-Recipe an. Pflicht: key, name, subject_type, sources, style. Optional: description, guards, llm, freshness_requirement, scope ("global"|"team", default "team"). Beispiel-sources: {"description": true, "frogs": {"enabled": true, "top_n": 3}, "canvas": {"enabled": true, "max_highlights": 2}}. Beispiel-style: {"address": "sie", "tone": "formal", "rhythm": "short", "extra_instruction": "Maximal 3 Saetze."}. Beispiel-llm: {"provider": "openai", "model": "gpt-4o-2024-08-06"}.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'key' => ['type' => 'string', 'description' => 'Eindeutiger Schluessel (snake_case empfohlen, z.B. "customer_brief").'],
                'name' => ['type' => 'string', 'description' => 'Display-Name.'],
                'subject_type' => ['type' => 'string', 'description' => 'Welcher Subject-Type wird bedient (z.B. "planner_project").'],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung wozu die Recipe gedacht ist.'],
                'sources' => [
                    'type' => 'object',
                    'description' => 'JSON: welche Fact/Edge-Quellen wie tief gesammelt werden. Sammler interpretiert die Keys, die er kennt.',
                ],
                'style' => [
                    'type' => 'object',
                    'description' => 'JSON: Stilparameter (address/tone/rhythm/extra_instruction). Recipe-Felder ueberschreiben Defaults.',
                ],
                'guards' => [
                    'type' => 'object',
                    'description' => 'Optional: Guard-Overrides als JSON. Default ist alles strikt true.',
                ],
                'llm' => [
                    'type' => 'object',
                    'description' => 'Optional: LLM-Praeferenz {"provider": "openai|anthropic|...", "model": "..."}. Beide Felder optional. Feed-Override sticht Recipe, Recipe sticht Config-Default.',
                ],
                'include_natures' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => ['state', 'movement', 'derivation']],
                    'description' => 'Optional: Fact-Naturen, die in die Prosa fliessen sollen. null/leer = alle. Beispiele: ["state"] fuer reinen Zustandsbericht (Wall-Display), ["movement","derivation"] fuer Change-Ticker, ["state","movement","derivation"] fuer Hybrid.',
                ],
                'since_window' => [
                    'type' => 'string',
                    'description' => 'Optional: Zeitfenster fuer "seit"-Berechnungen. Format "7d" / "1w" / "30d" / "1m" / "1y". Wenn gesetzt, greift der Collector konsequent now()-N Tage statt des letzten Outputs. Ohne Wert: Fallback = letzter Output. Fuer entity_pulse aktiviert es zusaetzlich Delta-Facts gegen die Baseline vor N Tagen.',
                ],
                'freshness_requirement' => [
                    'type' => 'string',
                    'enum' => ['live', 'snapshot', 'snapshot_with_live_topup'],
                    'description' => 'Optional: erwartete Frische-Strategie. Sammler entscheidet selbst, das ist nur Hinweis.',
                ],
                'scope' => [
                    'type' => 'string',
                    'enum' => ['global', 'team'],
                    'description' => 'Scope der Recipe. "global" = team_id null. "team" = aktuelles Team. Default "team".',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Default true.',
                ],
            ],
            'required' => ['key', 'name', 'subject_type', 'sources', 'style'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $key = trim((string) ($arguments['key'] ?? ''));
        $name = trim((string) ($arguments['name'] ?? ''));
        $subjectType = trim((string) ($arguments['subject_type'] ?? ''));
        $sources = $arguments['sources'] ?? null;
        $style = $arguments['style'] ?? null;
        if ($key === '' || $name === '' || $subjectType === '' || ! is_array($sources) || ! is_array($style)) {
            return ToolResult::error('VALIDATION_ERROR', 'key, name, subject_type, sources (object), style (object) sind Pflicht.');
        }

        $scope = $arguments['scope'] ?? 'team';
        $team = $context->team ?? $context->user->currentTeam ?? null;
        $teamId = $scope === 'global' ? null : $team?->id;
        if ($scope === 'team' && ! $teamId) {
            return ToolResult::error('VALIDATION_ERROR', 'scope=team aber kein Team-Kontext verfuegbar.');
        }

        $existing = VerbalizationRecipe::where('key', $key)
            ->where(function ($q) use ($teamId) {
                if ($teamId === null) {
                    $q->whereNull('team_id');
                } else {
                    $q->where('team_id', $teamId);
                }
            })->first();
        if ($existing) {
            return ToolResult::error('RECIPE_EXISTS', "Recipe '{$key}' im Scope existiert bereits (id={$existing->id}). Nutze PUT zum Update.");
        }

        $recipe = VerbalizationRecipe::create([
            'key' => $key,
            'name' => $name,
            'description' => $arguments['description'] ?? null,
            'subject_type' => $subjectType,
            'sources' => $sources,
            'style' => $style,
            'guards' => $arguments['guards'] ?? null,
            'llm' => $arguments['llm'] ?? null,
            'include_natures' => $arguments['include_natures'] ?? null,
            'since_window' => $arguments['since_window'] ?? null,
            'freshness_requirement' => $arguments['freshness_requirement'] ?? null,
            'team_id' => $teamId,
            'is_active' => $arguments['is_active'] ?? true,
            'created_by_user_id' => Auth::id() ?? $context->user->id ?? null,
        ]);

        return ToolResult::success([
            'id' => $recipe->id,
            'uuid' => $recipe->uuid,
            'key' => $recipe->key,
            'scope' => $teamId ? 'team' : 'global',
            'team_id' => $teamId,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize',
            'tags' => ['core', 'verbalization', 'recipes', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => false,
        ];
    }
}
