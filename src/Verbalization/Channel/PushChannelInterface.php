<?php

namespace Platform\Reporting\Verbalization\Channel;

use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;

/**
 * Push-Kanal — liefert einen einzelnen frischen Output aktiv aus (Obsidian-Datei
 * schreiben, Email versenden, Slack-Message senden, PDF ablegen).
 *
 * Pull-Kanaele (rss, web) implementieren dieses Interface NICHT — deren
 * Ausgabe wird beim URL-Abruf ueber render() erzeugt.
 */
interface PushChannelInterface extends ChannelRendererInterface
{
    /**
     * Liefert einen frischen Output an das konfigurierte Ziel aus.
     *
     * @return array{success: bool, ref?: string, error?: string}
     *   success: true wenn Delivery geklappt hat
     *   ref: Referenz zum ausgelieferten Objekt (z.B. Dateipfad, Message-ID)
     *   error: Fehlermeldung wenn success=false
     */
    public function deliver(
        VerbalizationChannel $channel,
        VerbalizationFeed $feed,
        VerbalizationOutput $output,
    ): array;
}
