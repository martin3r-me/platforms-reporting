<?php

namespace Platform\Reporting\Verbalization;

use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Core\Verbalization\GuardRails;
use Platform\Core\Verbalization\VerbalizationResult;

use Platform\Core\Verbalization\Contracts\ReportEngine;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Reporting\Verbalization\Recipe\RecipeResolver;

/**
 * Core-Implementierung des ReportEngine-Facades — delegiert 1:1 an die heutige
 * Engine (RecipeResolver + Verbalizer), die noch in Core liegt.
 *
 * Beim Extrahieren des reporting-Moduls wandert genau diese Klasse (samt
 * Verbalizer/RecipeResolver) ins Modul; das Binding zieht in den
 * reporting-ServiceProvider um. Consumer (planner etc.) ändern sich nicht.
 */
class ReportingEngine implements ReportEngine
{
    public function __construct(
        protected RecipeResolver $recipes,
        protected Verbalizer $verbalizer,
    ) {}

    public function resolveRecipe(
        string $key,
        ?int $teamId = null,
        ?string $subjectType = null,
    ): ?CollectionRecipe {
        return $this->recipes->resolve($key, $teamId, $subjectType);
    }

    public function verbalize(
        Subject $subject,
        ?StyleProfile $style = null,
        ?GuardRails $rails = null,
        ?string $providerKey = null,
        ?string $modelOverride = null,
        ?CollectionRecipe $recipe = null,
    ): VerbalizationResult {
        return $this->verbalizer->verbalize(
            subject: $subject,
            style: $style,
            rails: $rails,
            providerKey: $providerKey,
            modelOverride: $modelOverride,
            recipe: $recipe,
        );
    }
}
