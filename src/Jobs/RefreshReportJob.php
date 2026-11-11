<?php

namespace Platform\Reporting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Reporting\Models\VerbalizationFeed;
use Platform\Reporting\Reports\ReportStyle;
use Platform\Reporting\Verbalization\Feed\FeedService;

/**
 * „Jetzt erzeugen" aus dem Baukasten — ein Bericht kann viele Subjects (= LLM-Calls)
 * haben, darum nie im Request.
 */
class RefreshReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;
    public $tries = 1;

    public function __construct(
        public int $feedId,
        public bool $force = false,
    ) {}

    public function handle(FeedService $feeds): void
    {
        $feed = VerbalizationFeed::find($this->feedId);
        if (! $feed) {
            return;
        }

        $result = $feeds->refresh($feed, ReportStyle::for($feed->team, $feed->subject_type), $this->force);

        if (! empty($result['errors'])) {
            Log::warning('[reporting] Bericht mit Fehlern erzeugt', [
                'feed_id' => $feed->id,
                'errors' => array_slice($result['errors'], 0, 10),
            ]);
        }
    }
}
