<?php

namespace Platform\Reporting\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Reporting\Verbalization\Feed\FeedService;

/**
 * php artisan reporting:refresh-feeds [--cadence=daily|weekly|all] [--feed=ID]
 *
 * Modul-Pendant zu Cores `verbalization:refresh-feeds`. Iteriert über alle
 * aktiven Feeds mit passender refresh_strategy und triggert die Modul-
 * FeedService::refresh().
 *
 * Koexistenz mit Core: der FeedService dedupliziert über den state_hash des
 * jüngsten Outputs (Skip vor dem LLM-Call). Der Laravel-Scheduler führt fällige
 * Commands sequenziell in einem Prozess aus; Core (bootet zuerst) läuft vor
 * diesem Command, erzeugt die Outputs — dieser Lauf sieht denselben Hash und
 * skippt. Kein Doppel-Output. Beim Contract-Schritt fällt Cores Command +
 * Schedule weg und dieses hier übernimmt allein.
 */
class RefreshReportingFeedsCommand extends Command
{
    protected $signature = 'reporting:refresh-feeds
        {--cadence=all : daily | weekly | all}
        {--feed= : ID oder UUID eines einzelnen Feeds}';

    protected $description = 'Triggert Verbalisierung aller aktiven Feeds mit passender refresh_strategy (Reporting-Modul).';

    public function handle(FeedService $service): int
    {
        $cadence = $this->option('cadence');
        $feedFilter = $this->option('feed');

        $q = VerbalizationFeed::query()->where('is_active', true);

        if ($feedFilter) {
            $q->where(function ($q) use ($feedFilter) {
                $q->where('id', is_numeric($feedFilter) ? (int) $feedFilter : -1)
                  ->orWhere('uuid', $feedFilter);
            });
        } else {
            $strategies = match ($cadence) {
                'daily' => ['cron_daily'],
                'weekly' => ['cron_weekly'],
                default => ['cron_daily', 'cron_weekly'],
            };
            $q->whereIn('refresh_strategy', $strategies);
        }

        $feeds = $q->get();
        if ($feeds->isEmpty()) {
            $this->info('Keine Feeds zum Refreshen.');
            return self::SUCCESS;
        }

        $resolver = app(SemanticLayerResolver::class);

        $totalOutputs = 0;
        $totalErrors = 0;
        foreach ($feeds as $feed) {
            $this->info("Refresh Feed #{$feed->id} '{$feed->title}' ({$feed->refresh_strategy})...");

            $style = StyleProfile::formal();
            try {
                $team = $feed->team;
                $resolved = $resolver->resolveFor($team, $feed->subject_type ?? 'planner_project');
                if ($resolved->rendered_block) {
                    $style = $style->withSemanticLayer($resolved->rendered_block);
                }
            } catch (\Throwable $e) {
                // Semantic Layer optional
            }

            $result = $service->refresh($feed, $style);
            $totalOutputs += $result['outputs_created'];
            $totalErrors += count($result['errors']);

            $this->line("  Subjects: {$result['subjects_resolved']}, Outputs: {$result['outputs_created']}, Errors: " . count($result['errors']));
            foreach ($result['errors'] as $err) {
                $this->warn('  ! ' . $err);
            }
        }

        $this->info("Fertig. Feeds: " . $feeds->count() . ", Outputs gesamt: {$totalOutputs}, Fehler: {$totalErrors}");
        return self::SUCCESS;
    }
}
