<?php

namespace Platform\Reporting\Verbalization\Baseline;

use Illuminate\Support\Carbon;
use Platform\Core\Models\VerbalizationBaseline;

/**
 * Verwaltet Zeitreihen-Snapshots fuer Metriken pro Subject und ermittelt
 * ad-hoc Deltas ueber beliebige Fenster.
 *
 * Zwei Aufgaben:
 *   - snapshot()  → aktuellen Metric-Bag als Baseline persistieren (nach Refresh)
 *   - deltaFor()  → letzten Snapshot vor now()-window suchen und Deltas pro Key liefern
 *
 * Baseline ist bewusst Feed-agnostisch: ein Kunden-Pulse und ein interner Pulse
 * am gleichen Subject teilen die Zeitreihe. Fenster ist Konsum-lokal.
 */
class BaselineService
{
    /**
     * Persistiert einen neuen Snapshot fuer (subject_type, subject_id).
     *
     * @param array<string, int|float> $metrics
     */
    public function snapshot(string $subjectType, string $subjectId, array $metrics): void
    {
        if (empty($metrics)) {
            return;
        }
        // Nur numerische Werte speichern — Baseline hat keinen Text.
        $clean = [];
        foreach ($metrics as $key => $value) {
            if (is_numeric($value)) {
                $clean[$key] = (float) $value;
            }
        }
        if (empty($clean)) {
            return;
        }
        VerbalizationBaseline::create([
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectId,
            'metrics' => $clean,
            'captured_at' => Carbon::now(),
        ]);
    }

    /**
     * Findet den juengsten Snapshot, dessen captured_at vor (now - $daysBack) liegt,
     * und liefert pro Metric-Key die Werte (current/baseline/delta).
     *
     * @param array<string, int|float> $currentMetrics
     * @return array<string, array{current: float, baseline: float, delta: float, delta_pct: float|null}>
     */
    public function deltaFor(
        string $subjectType,
        string $subjectId,
        int $daysBack,
        array $currentMetrics,
    ): array {
        if ($daysBack <= 0 || empty($currentMetrics)) {
            return [];
        }
        $cutoff = Carbon::now()->subDays($daysBack);

        $baseline = VerbalizationBaseline::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', (string) $subjectId)
            ->where('captured_at', '<=', $cutoff)
            ->orderByDesc('captured_at')
            ->first();

        if (! $baseline) {
            return [];
        }
        $baseMetrics = (array) $baseline->metrics;

        $out = [];
        foreach ($currentMetrics as $key => $current) {
            if (! is_numeric($current)) {
                continue;
            }
            if (! array_key_exists($key, $baseMetrics)) {
                continue;
            }
            $currentF = (float) $current;
            $baseF = (float) $baseMetrics[$key];
            $delta = $currentF - $baseF;
            $pct = null;
            if ($baseF != 0.0) {
                $pct = round(($delta / abs($baseF)) * 100.0, 1);
            }
            $out[$key] = [
                'current' => $currentF,
                'baseline' => $baseF,
                'delta' => $delta,
                'delta_pct' => $pct,
            ];
        }
        return $out;
    }

    /**
     * Uebersetzt Fenster-Kurzformen ("7d", "1w", "30d", "1m", "1y") in Tage.
     * Rueckgabe 0 bei ungueltigem Input.
     */
    public static function parseWindowDays(?string $window): int
    {
        if (! $window) {
            return 0;
        }
        if (! preg_match('/^(\d+)\s*([dwmy])$/i', trim($window), $m)) {
            return 0;
        }
        $n = max(1, (int) $m[1]);
        return match (strtolower($m[2])) {
            'd' => $n,
            'w' => $n * 7,
            'm' => $n * 30,
            'y' => $n * 365,
            default => 0,
        };
    }
}
