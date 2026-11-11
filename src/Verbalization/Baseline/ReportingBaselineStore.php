<?php

namespace Platform\Reporting\Verbalization\Baseline;

use Platform\Core\Verbalization\Contracts\BaselineStore;

/**
 * Bedient Cores BaselineStore-Port mit dem Modul-BaselineService. Eigene Klasse
 * (statt BaselineService implements …), damit der BaselineService auch gegen
 * ein Core ohne den Contract lädt.
 */
class ReportingBaselineStore implements BaselineStore
{
    public function __construct(protected BaselineService $baselines) {}

    public function deltaFor(string $subjectType, string $subjectId, int $daysBack, array $currentMetrics): array
    {
        return $this->baselines->deltaFor($subjectType, $subjectId, $daysBack, $currentMetrics);
    }
}
