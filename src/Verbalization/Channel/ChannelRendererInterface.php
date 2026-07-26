<?php

namespace Platform\Reporting\Verbalization\Channel;

use Illuminate\Support\Collection;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;

/**
 * Renderer fuer einen Kanal-Typ (rss, email, pdf, slack, webhook, ...).
 *
 * Der Renderer verwandelt die Reihe von Prosa-Outputs (VerbalizationOutput) in
 * ein kanal-spezifisches Rohformat (Atom-XML, HTML-Email, PDF-Binary, Slack-JSON, ...).
 * Er ist NICHT verantwortlich fuer Delivery — das ist Sache eines separaten
 * Delivery-Dispatchers in Phase 2. Fuer Pull-Kanaele (rss) genuegt der Renderer.
 */
interface ChannelRendererInterface
{
    /**
     * Welcher Kanal-Typ wird bedient? (z.B. "rss", "email", "pdf")
     */
    public function type(): string;

    /**
     * HTTP-Content-Type der gerenderten Ausgabe.
     * (Fuer Nicht-HTTP-Kanaele MIME-Type der Payload.)
     */
    public function contentType(): string;

    /**
     * Rendert die Prosa-Items fuer diesen Kanal.
     *
     * @param VerbalizationChannel $channel Kanal-Instanz mit Konfiguration
     * @param VerbalizationFeed    $feed    Report-Referenz (Titel, Beschreibung, ...)
     * @param Collection            $items  VerbalizationOutput-Instanzen (bereits gefiltert/sortiert)
     */
    public function render(VerbalizationChannel $channel, VerbalizationFeed $feed, Collection $items): string;
}
