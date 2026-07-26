<?php

namespace Platform\Reporting\Verbalization\Feed;

use Illuminate\Support\Collection;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;

/**
 * Atom 1.0 Renderer fuer VerbalizationFeeds.
 *
 * Atom statt RSS 2.0, weil:
 *  - strengere Spec (pflichtfelder klar)
 *  - explizites updated/published statt schwammigem pubDate
 *  - utf-8 nativ, kein <link> doppeldeutig
 *
 * Reader, die Atom nicht koennen, sind 2026 Vergangenheit (Feedly, NetNewsWire,
 * Slack/Teams-Integrationen verstehen beide).
 */
class AtomFeedRenderer
{
    public function __construct(
        protected string $publicBaseUrl,
    ) {}

    public function render(VerbalizationFeed $feed, Collection $items): string
    {
        $self = rtrim($this->publicBaseUrl, '/') . '/feed/' . $feed->uuid . '.xml';
        $updated = $items->max('created_at') ?? $feed->updated_at ?? now();
        $updatedAtom = $updated->toAtomString();

        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <id>urn:uuid:' . $this->xml($feed->uuid) . '</id>' . "\n";
        $xml .= '  <title>' . $this->xml($feed->title) . '</title>' . "\n";
        if ($feed->description) {
            $xml .= '  <subtitle>' . $this->xml($feed->description) . '</subtitle>' . "\n";
        }
        $xml .= '  <updated>' . $updatedAtom . '</updated>' . "\n";
        $xml .= '  <link rel="self" type="application/atom+xml" href="' . $this->xml($self) . '"/>' . "\n";

        foreach ($items as $item) {
            /** @var VerbalizationOutput $item */
            $xml .= $this->renderEntry($item);
        }

        $xml .= '</feed>' . "\n";
        return $xml;
    }

    protected function renderEntry(VerbalizationOutput $item): string
    {
        $itemId = 'urn:uuid:' . $item->uuid;
        $when = $item->created_at?->toAtomString() ?? now()->toAtomString();
        $title = $this->buildTitle($item);
        $contentHtml = $this->markdownToHtml($item->prose ?? '');

        $entry = '  <entry>' . "\n";
        $entry .= '    <id>' . $this->xml($itemId) . '</id>' . "\n";
        $entry .= '    <title>' . $this->xml($title) . '</title>' . "\n";
        $entry .= '    <updated>' . $when . '</updated>' . "\n";
        $entry .= '    <published>' . $when . '</published>' . "\n";
        $entry .= '    <author><name>' . $this->xml('KyberOS Verbalizer') . '</name></author>' . "\n";
        $entry .= '    <category term="' . $this->xml($item->subject_type ?? '') . '"/>' . "\n";
        $entry .= '    <content type="html">' . $this->xml($contentHtml) . '</content>' . "\n";
        $entry .= '  </entry>' . "\n";
        return $entry;
    }

    protected function buildTitle(VerbalizationOutput $item): string
    {
        $label = trim($item->subject_label ?? '') !== ''
            ? $item->subject_label
            : ($item->subject_type . '#' . $item->subject_id);
        $date = $item->created_at?->format('d.m.Y') ?? '';
        return $date !== '' ? "{$label} — Stand {$date}" : $label;
    }

    /**
     * Minimaler Markdown→HTML-Konverter — gerade genug fuer unsere Outputs.
     * Headings, fett, kursiv, listen, absaetze.
     *
     * Wir bringen bewusst keine Library mit (kein zusaetzliches composer-Paket).
     * Reader rendern HTML-Content; reines Markdown wuerden viele Reader nicht
     * korrekt interpretieren.
     */
    protected function markdownToHtml(string $md): string
    {
        $lines = preg_split('/\r?\n/', $md);
        $out = [];
        $inList = false;
        $paragraph = [];

        $flushParagraph = function () use (&$out, &$paragraph) {
            if (! empty($paragraph)) {
                $text = $this->inlineMarkdown(implode(' ', $paragraph));
                $out[] = '<p>' . $text . '</p>';
                $paragraph = [];
            }
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $flushParagraph();
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                continue;
            }
            if (preg_match('/^###\s+(.+)$/u', $trim, $m)) {
                $flushParagraph();
                if ($inList) { $out[] = '</ul>'; $inList = false; }
                $out[] = '<h3>' . $this->inlineMarkdown($m[1]) . '</h3>';
                continue;
            }
            if (preg_match('/^##\s+(.+)$/u', $trim, $m)) {
                $flushParagraph();
                if ($inList) { $out[] = '</ul>'; $inList = false; }
                $out[] = '<h2>' . $this->inlineMarkdown($m[1]) . '</h2>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/u', $trim, $m)) {
                $flushParagraph();
                if (! $inList) {
                    $out[] = '<ul>';
                    $inList = true;
                }
                $out[] = '<li>' . $this->inlineMarkdown($m[1]) . '</li>';
                continue;
            }
            $paragraph[] = $trim;
        }
        $flushParagraph();
        if ($inList) {
            $out[] = '</ul>';
        }
        return implode("\n", $out);
    }

    protected function inlineMarkdown(string $text): string
    {
        // **fett**
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
        // *kursiv*
        $text = preg_replace('/(?<![\*])\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '<em>$1</em>', $text);
        return $text;
    }

    protected function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
