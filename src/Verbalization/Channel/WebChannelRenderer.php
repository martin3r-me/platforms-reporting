<?php

namespace Platform\Reporting\Verbalization\Channel;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;

/**
 * Pull-Kanal: rendert Feed-Outputs als eigenstaendige HTML-Seite. Ohne Login,
 * ohne Layout-Abhaengigkeit — der Kunde bekommt einen Link, oeffnet ihn im
 * Browser, sieht den Bericht "schick und munter".
 *
 * Design bewusst schlank:
 *   - System-Font-Stack, keine externen Assets
 *   - Kompaktes Inline-CSS, druckbar
 *   - Timeline: neueste Ausgabe oben
 *   - Markdown → HTML via Str::markdown (CommonMark, sicher)
 *
 * Config aktuell leer — spaeter koennen Farb-Themes / Logos hier rein.
 */
class WebChannelRenderer implements ChannelRendererInterface
{
    public function type(): string
    {
        return 'web';
    }

    public function contentType(): string
    {
        return 'text/html; charset=utf-8';
    }

    public function render(VerbalizationChannel $channel, VerbalizationFeed $feed, Collection $items): string
    {
        $title = htmlspecialchars((string) $feed->title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = trim((string) ($feed->description ?? ''));
        $lastRefresh = $feed->last_refreshed_at?->format('d.m.Y H:i');

        $descriptionHtml = $description !== ''
            ? '<div class="desc">' . htmlspecialchars($description, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</div>'
            : '';
        $refreshHtml = $lastRefresh
            ? '<div class="refresh">Zuletzt aktualisiert: ' . htmlspecialchars($lastRefresh, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</div>'
            : '';

        $itemsHtml = '';
        foreach ($items as $item) {
            /** @var VerbalizationOutput $item */
            $itemsHtml .= $this->renderItem($item);
        }
        if ($itemsHtml === '') {
            $itemsHtml = '<div class="empty">Noch kein Bericht erzeugt.</div>';
        }

        $css = $this->css();

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{$title}</title>
<style>{$css}</style>
</head>
<body>
<main>
    <header>
        <div class="meta">Bericht</div>
        <h1>{$title}</h1>
        {$descriptionHtml}
        {$refreshHtml}
    </header>
    <section class="timeline">
        {$itemsHtml}
    </section>
    <footer>
        <div class="foot-meta">Automatisch erzeugter Bericht &middot; nicht indexiert</div>
    </footer>
</main>
</body>
</html>
HTML;
    }

    protected function renderItem(VerbalizationOutput $output): string
    {
        $when = $output->created_at?->format('d.m.Y H:i') ?? '';
        $recipe = htmlspecialchars((string) ($output->recipe_key ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $subjectLabel = htmlspecialchars((string) ($output->subject_label ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Markdown → HTML (sicher; CommonMark strippt gefaehrliche Konstrukte nicht
        // aggressiv, aber Prosa wird nur vom LLM erzeugt — Trust-Level ist bekannt).
        $prose = (string) ($output->prose ?? '');
        try {
            $html = Str::markdown($prose);
        } catch (\Throwable $e) {
            $html = '<p>' . nl2br(htmlspecialchars($prose, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</p>';
        }

        return <<<HTML
<article>
    <div class="head">
        <time>{$when}</time>
        <span class="pill">{$recipe}</span>
    </div>
    <div class="body">
        {$html}
    </div>
    <div class="sublabel">{$subjectLabel}</div>
</article>
HTML;
    }

    protected function css(): string
    {
        return <<<CSS
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 15px; line-height: 1.6;
    color: #1f2937; background: #f8fafc;
    -webkit-font-smoothing: antialiased;
}
main { max-width: 780px; margin: 0 auto; padding: 32px 20px 64px; }
header { margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
header .meta { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
header h1 { font-size: 28px; margin: 0 0 8px; letter-spacing: -0.01em; color: #0f172a; }
header .desc { color: #4b5563; font-size: 14px; margin: 6px 0 12px; }
header .refresh { font-size: 12px; color: #6b7280; }
.timeline { display: flex; flex-direction: column; gap: 20px; }
article { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 22px 24px; }
article .head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; gap: 12px; flex-wrap: wrap; }
article time { font-size: 12px; color: #6b7280; font-variant-numeric: tabular-nums; }
article .pill { font-size: 11px; padding: 2px 10px; border-radius: 999px; background: #eef2ff; color: #4338ca; letter-spacing: 0.02em; }
article .body { color: #1f2937; }
article .body h1, article .body h2, article .body h3 { color: #0f172a; margin-top: 20px; margin-bottom: 8px; letter-spacing: -0.01em; }
article .body h1 { font-size: 20px; }
article .body h2 { font-size: 17px; }
article .body h3 { font-size: 15px; text-transform: none; }
article .body p { margin: 10px 0; }
article .body strong { color: #0f172a; }
article .body ul, article .body ol { padding-left: 22px; margin: 8px 0; }
article .body code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-size: 13px; }
article .sublabel { margin-top: 14px; padding-top: 12px; border-top: 1px dashed #e5e7eb; font-size: 11px; color: #6b7280; }
.empty { padding: 40px; text-align: center; color: #9ca3af; }
footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
.foot-meta { font-size: 11px; color: #9ca3af; text-align: center; }
@media print {
    body { background: #ffffff; }
    article { break-inside: avoid; box-shadow: none; }
}
CSS;
    }
}
