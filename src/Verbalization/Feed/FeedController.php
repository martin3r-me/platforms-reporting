<?php

namespace Platform\Reporting\Verbalization\Feed;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Verbalization\Channel\ChannelRendererRegistry;

/**
 * Public Endpoint fuer Verbalization-Kanaele (Pull-Kanaele wie RSS/Atom).
 *
 * Sicherheits-Modell: opake UUID in der URL. Keine Loginpflicht.
 * Wer den Token kennt, sieht den Kanal — wie iCal-Links, GitHub-Atom-Tokens etc.
 *
 * Historisch war der Token die Feed-UUID; seit Phase 1 der Kanal-Ebene sind
 * URLs eigentlich Kanal-URLs. Die Data-Migration hat sichergestellt, dass jeder
 * bestehende Feed einen RSS-Kanal mit derselben UUID hat — bestehende
 * Reader-URLs bleiben stabil.
 *
 * Headers werden vom Kanal-Renderer beeinflusst (Content-Type).
 * ETag / Last-Modified bleiben fuer Polling-Effizienz.
 */
class FeedController
{
    public function __construct(
        protected FeedService $service,
        protected ChannelRendererRegistry $renderers,
        protected AtomFeedRenderer $rssFallback,
    ) {}

    public function __invoke(Request $request, string $token): Response
    {
        $token = preg_replace('/\.xml$/', '', $token);

        // Zuerst als Kanal-UUID auflösen (Phase 1: RSS-Kanal mit uuid == alter feed.uuid).
        $channel = VerbalizationChannel::where('uuid', $token)
            ->where('is_active', true)
            ->with('feed')
            ->first();

        $feed = $channel?->feed;

        // Fallback: alter Weg via Feed-UUID (falls Kanal-Migration nicht lief oder
        // externe URLs vor der Migration noch existieren).
        if (! $feed) {
            $feed = VerbalizationFeed::where('uuid', $token)
                ->where('is_active', true)
                ->first();
        }

        if (! $feed) {
            return new Response('Feed not found.', 404, ['Content-Type' => 'text/plain']);
        }
        if ($feed->access !== 'public') {
            return new Response('Feed is not public.', 403, ['Content-Type' => 'text/plain']);
        }

        $items = $this->service->itemsForFeed($feed);

        // Kanal-Renderer aufloesen; fuer null-Kanal (Fallback-Pfad) direkt AtomRenderer.
        if ($channel) {
            $renderer = $this->renderers->resolve($channel->type);
            if ($renderer) {
                $body = $renderer->render($channel, $feed, $items);
                $contentType = $renderer->contentType();
            } else {
                $body = $this->rssFallback->render($feed, $items);
                $contentType = 'application/atom+xml; charset=utf-8';
            }
        } else {
            $body = $this->rssFallback->render($feed, $items);
            $contentType = 'application/atom+xml; charset=utf-8';
        }

        $lastModified = $items->max('created_at') ?? $feed->updated_at ?? now();
        $etag = '"' . md5($token . ':' . $lastModified->getTimestamp() . ':' . $items->count()) . '"';

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return (new Response('', 304))
                ->header('ETag', $etag)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return (new Response($body, 200))
            ->header('Content-Type', $contentType)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'public, max-age=300')
            ->header('ETag', $etag)
            ->header('Last-Modified', $lastModified->toRfc7231String());
    }
}
