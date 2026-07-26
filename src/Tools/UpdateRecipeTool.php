<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationRecipe;

/**
 * core.verbalization.recipes.PUT
 *
 * Aktualisiert eine bestehende Recipe (per id ODER key+scope).
 * Felder, die nicht gesetzt werden, bleiben unveraendert.
 */
class UpdateRecipeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.verbalization.recipes.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /verbalization/recipes/{id} - Aktualisiert eine Recipe. Adressierung: id ODER (key + scope). Nur uebergebene Felder werden aktualisiert; alles andere bleibt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Direkter Treffer.'],
                'key' => ['type' => 'string', 'description' => 'Alternativ zu id: key (kombiniert mit scope).'],
                'scope' => ['type' => 'string', 'enum' => ['global', 'team'], 'description' => 'Bei key-Adressierung: scope (default "team").'],
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'sources' => ['type' => 'object'],
                'style' => ['type' => 'object'],
                'guards' => ['type' => 'object'],
                'llm' => ['type' => 'object', 'description' => 'LLM-Praeferenz {"provider": "...", "model": "..."}. null loescht die Praeferenz und faellt auf Config-Default zurueck.'],
                'include_natures' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => ['state', 'movement', 'derivation']],
                    'description' => 'Fact-Naturen-Filter. null/leer = alle Naturen zugelassen.',
                ],
                'since_window' => ['type' => 'string', 'description' => 'Zeitfenster ("7d"/"1w"/"30d"/"1m"/"1y"). null = Fallback letzter Output.'],
                'freshness_requirement' => ['type' => 'string', 'enum' => ['live', 'snapshot', 'snapshot_with_live_topup']],
                'is_active' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $recipe = null;
        if (! empty($arguments['id'])) {
            $recipe = VerbalizationRecipe::find((int) $arguments['id']);
        } elseif (! empty($arguments['key'])) {
            $scope = $arguments['scope'] ?? 'team';
            $team = $context->team ?? $context->user->currentTeam ?? null;
            $teamId = $scope === 'global' ? null : $team?->id;
            $recipe = VerbalizationRecipe::where('key', $arguments['key'])
                ->where(function ($q) use ($teamId) {
                    if ($teamId === null) {
                        $q->whereNull('team_id');
                    } else {
                        $q->where('team_id', $teamId);
                    }
                })->first();
        }
        if (! $recipe) {
            return ToolResult::error('RECIPE_NOT_FOUND', 'Recipe nicht gefunden. Gib id oder key+scope an.');
        }

        foreach (['name', 'description', 'sources', 'style', 'guards', 'llm', 'include_natures', 'since_window', 'freshness_requirement', 'is_active'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $recipe->{$field} = $arguments[$field];
            }
        }
        $recipe->save();

        return ToolResult::success([
            'id' => $recipe->id,
            'key' => $recipe->key,
            'scope' => $recipe->team_id ? 'team' : 'global',
            'team_id' => $recipe->team_id,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize',
            'tags' => ['core', 'verbalization', 'recipes', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
