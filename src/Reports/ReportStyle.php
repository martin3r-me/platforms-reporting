<?php

namespace Platform\Reporting\Reports;

use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\Verbalization\StyleProfile;

/**
 * Grundstil eines Berichts: formal + Semantic Layer des Teams (Perspektive, Ton,
 * Negativ-Raum). Die Perspektive (Recipe) legt ihren Stil später darüber.
 */
class ReportStyle
{
    public static function for(mixed $team, ?string $subjectType): StyleProfile
    {
        $style = StyleProfile::formal();

        try {
            $resolved = app(SemanticLayerResolver::class)->resolveFor($team, $subjectType ?? 'planner_project');
            if ($resolved->rendered_block) {
                $style = $style->withSemanticLayer($resolved->rendered_block);
            }
        } catch (\Throwable $e) {
            // Semantic Layer ist optional.
        }

        return $style;
    }
}
