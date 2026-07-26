<?php

namespace Platform\Reporting\Verbalization\Channel;

use Illuminate\Support\Collection;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Verbalization\Feed\AtomFeedRenderer;

/**
 * RSS/Atom-Kanal — delegiert an den bestehenden AtomFeedRenderer. Damit bleibt
 * das jahrelang etablierte Rendering unveraendert; die Channel-Ebene fuegt nur
 * das saubere Interface hinzu.
 */
class RssChannelRenderer implements ChannelRendererInterface
{
    public function __construct(protected AtomFeedRenderer $inner) {}

    public function type(): string
    {
        return 'rss';
    }

    public function contentType(): string
    {
        return 'application/atom+xml; charset=utf-8';
    }

    public function render(VerbalizationChannel $channel, VerbalizationFeed $feed, Collection $items): string
    {
        return $this->inner->render($feed, $items);
    }
}
