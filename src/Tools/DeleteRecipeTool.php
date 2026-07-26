<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationRecipe;

/**
 * core.verbalization.recipes.DELETE
 *
 * Loescht eine Recipe (hard delete — kein soft-delete im V1).
 */
class DeleteRecipeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.verbalization.recipes.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /verbalization/recipes/{id} - Loescht eine Recipe. Adressierung wie bei PUT: id ODER (key + scope). confirm=true ist Pflicht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'key' => ['type' => 'string'],
                'scope' => ['type' => 'string', 'enum' => ['global', 'team']],
                'confirm' => ['type' => 'boolean', 'description' => 'Muss explizit true sein.'],
            ],
            'required' => ['confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }
        if (empty($arguments['confirm'])) {
            return ToolResult::error('VALIDATION_ERROR', 'confirm=true ist Pflicht zum Loeschen.');
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
            return ToolResult::error('RECIPE_NOT_FOUND', 'Recipe nicht gefunden.');
        }

        $info = ['id' => $recipe->id, 'key' => $recipe->key];
        $recipe->delete();

        return ToolResult::success(['deleted' => $info]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize',
            'tags' => ['core', 'verbalization', 'recipes', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => true,
        ];
    }
}
