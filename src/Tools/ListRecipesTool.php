<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationRecipe;

/**
 * core.verbalization.recipes.LIST
 *
 * Liste aller verfuegbaren Recipes — global + team-spezifisch.
 * Wenn subject_type angegeben, nach diesem Typ gefiltert.
 */
class ListRecipesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.verbalization.recipes.LIST';
    }

    public function getDescription(): string
    {
        return 'LIST /verbalization/recipes - Listet Verbalization-Recipes. Optional: subject_type-Filter (z.B. "planner_project"), include_global (default true). Recipes sind globale oder team-spezifische Sammel-und-Verbalisierungs-Auftraege fuer das Sprachorgan.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'subject_type' => [
                    'type' => 'string',
                    'description' => 'Optional: nur Recipes fuer diesen Subject-Type listen.',
                ],
                'include_global' => [
                    'type' => 'boolean',
                    'description' => 'Globale Recipes mitanzeigen (Default true).',
                ],
                'include_inactive' => [
                    'type' => 'boolean',
                    'description' => 'Auch inaktive Recipes mitanzeigen (Default false).',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }
        $team = $context->team ?? $context->user->currentTeam ?? null;

        $subjectType = $arguments['subject_type'] ?? null;
        $includeGlobal = (bool) ($arguments['include_global'] ?? true);
        $includeInactive = (bool) ($arguments['include_inactive'] ?? false);

        $q = VerbalizationRecipe::query();
        if (! $includeInactive) {
            $q->where('is_active', true);
        }
        if ($subjectType) {
            $q->where('subject_type', $subjectType);
        }
        $q->where(function ($q) use ($team, $includeGlobal) {
            if ($team) {
                $q->where('team_id', $team->id);
            }
            if ($includeGlobal) {
                $q->orWhereNull('team_id');
            }
        });

        $rows = $q->orderBy('subject_type')->orderBy('key')->get();

        $items = $rows->map(fn (VerbalizationRecipe $r) => [
            'id' => $r->id,
            'key' => $r->key,
            'name' => $r->name,
            'description' => $r->description,
            'subject_type' => $r->subject_type,
            'team_id' => $r->team_id,
            'scope' => $r->team_id ? 'team' : 'global',
            'is_active' => $r->is_active,
            'freshness_requirement' => $r->freshness_requirement,
            'sources' => $r->sources,
            'style' => $r->style,
            'guards' => $r->guards,
            'llm' => $r->llm,
            'include_natures' => $r->include_natures,
            'since_window' => $r->since_window,
        ])->all();

        return ToolResult::success([
            'recipes' => $items,
            'count' => count($items),
            'team_id' => $team?->id,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize',
            'tags' => ['core', 'verbalization', 'recipes', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
