<?php

namespace Platform\Reporting\Verbalization\Recipe;

use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;

use Platform\Core\Models\VerbalizationRecipe;

/**
 * Sucht eine Recipe anhand von key + Team-Scope.
 *
 * Lookup-Reihenfolge:
 *  1. Team-spezifisch (team_id = $teamId, is_active)
 *  2. Global (team_id null, is_active)
 *
 * Damit kann ein Team eine globale Recipe lokal "uebersteuern".
 */
class RecipeResolver
{
    public function resolve(string $key, ?int $teamId = null, ?string $subjectType = null): ?CollectionRecipe
    {
        $q = VerbalizationRecipe::query()
            ->where('key', $key)
            ->where('is_active', true);
        if ($subjectType) {
            $q->where('subject_type', $subjectType);
        }

        // Team-Scope hat Vorrang vor Global.
        $teamScoped = $teamId
            ? (clone $q)->where('team_id', $teamId)->first()
            : null;
        if ($teamScoped) {
            return CollectionRecipe::fromModel($teamScoped);
        }

        $global = (clone $q)->whereNull('team_id')->first();
        return $global ? CollectionRecipe::fromModel($global) : null;
    }

    /**
     * Liste der verfuegbaren Recipes fuer ein Team + Subject-Type
     * (Team-Eintraege ueberschreiben gleichnamige Globals).
     *
     * @return CollectionRecipe[]
     */
    public function listFor(?int $teamId, ?string $subjectType = null): array
    {
        $q = VerbalizationRecipe::query()->where('is_active', true);
        if ($subjectType) {
            $q->where('subject_type', $subjectType);
        }
        $q->where(function ($q) use ($teamId) {
            $q->whereNull('team_id');
            if ($teamId) {
                $q->orWhere('team_id', $teamId);
            }
        });

        $rows = $q->orderBy('key')->get();

        // Wenn team-scoped existiert, global mit gleichem key droppen.
        $byKey = [];
        foreach ($rows as $r) {
            $existing = $byKey[$r->key] ?? null;
            if (! $existing || $r->team_id !== null) {
                $byKey[$r->key] = $r;
            }
        }

        return array_map(fn ($m) => CollectionRecipe::fromModel($m), array_values($byKey));
    }
}
