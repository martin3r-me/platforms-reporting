<?php

namespace Platform\Reporting\Verbalization\Channel;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;
use Platform\Core\Services\ObsidianStorageService;

/**
 * Push-Kanal: Verbalization-Outputs als Markdown-Dateien in einen Obsidian-Vault.
 *
 * Config:
 *   - vault_id (int, required)       — welcher ObsidianVault
 *   - folder (string, required)      — Pfad im Vault (z.B. "Berichte/BHG.DIGITAL")
 *   - filename_template (string)     — Optional. Placeholder: {date}, {feed_title},
 *                                       {subject_label}, {recipe}. Default:
 *                                       "{date} — {feed_title}.md"
 *   - include_frontmatter (bool)     — Default true. YAML-Frontmatter mit Meta-Daten.
 *
 * Ein Output = eine neue Datei. Historisierung automatisch durch Dateien.
 */
class ObsidianChannelRenderer implements PushChannelInterface
{
    public function __construct(protected ObsidianStorageService $storage) {}

    public function type(): string
    {
        return 'obsidian';
    }

    public function contentType(): string
    {
        return 'text/markdown; charset=utf-8';
    }

    /**
     * render() ist fuer Pull-Konsumenten (z.B. Preview). Push-Delivery nutzt
     * deliver() direkt mit einem einzelnen Output.
     */
    public function render(VerbalizationChannel $channel, VerbalizationFeed $feed, Collection $items): string
    {
        $latest = $items->first();
        if (! $latest instanceof VerbalizationOutput) {
            return "# {$feed->title}\n\n*Noch kein Output.*\n";
        }
        return $this->buildMarkdown($channel, $feed, $latest);
    }

    public function deliver(
        VerbalizationChannel $channel,
        VerbalizationFeed $feed,
        VerbalizationOutput $output,
    ): array {
        $config = (array) ($channel->config ?? []);
        $vaultId = (int) ($config['vault_id'] ?? 0);
        $folder = trim((string) ($config['folder'] ?? ''), '/');
        if ($vaultId <= 0 || $folder === '') {
            return ['success' => false, 'error' => 'Obsidian-Channel benoetigt vault_id und folder in config.'];
        }

        $vault = ObsidianVault::find($vaultId);
        if (! $vault) {
            return ['success' => false, 'error' => "ObsidianVault #{$vaultId} nicht gefunden."];
        }

        $filename = $this->buildFilename($channel, $feed, $output);
        $path = $folder . '/' . $filename;
        $content = $this->buildMarkdown($channel, $feed, $output);

        try {
            $this->storage->writeFile($vault, $path, $content);
            return ['success' => true, 'ref' => $vault->slug . ':' . $path];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function buildMarkdown(
        VerbalizationChannel $channel,
        VerbalizationFeed $feed,
        VerbalizationOutput $output,
    ): string {
        $config = (array) ($channel->config ?? []);
        $includeFrontmatter = (bool) ($config['include_frontmatter'] ?? true);

        $parts = [];
        if ($includeFrontmatter) {
            $parts[] = $this->buildFrontmatter($feed, $output);
        }
        $parts[] = trim((string) $output->prose);
        $parts[] = '';
        return implode("\n", $parts);
    }

    protected function buildFrontmatter(VerbalizationFeed $feed, VerbalizationOutput $output): string
    {
        $data = [
            'title' => $this->safeYamlValue($feed->title),
            'date' => $output->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'subject_type' => $output->subject_type,
            'subject_id' => $output->subject_id,
            'subject_label' => $this->safeYamlValue($output->subject_label ?? ''),
            'recipe' => $output->recipe_key,
            'llm_model' => $output->llm_model ?? '',
            'llm_provider' => $output->llm_provider ?? '',
            'feed_uuid' => $feed->uuid,
        ];

        $lines = ['---'];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $lines[] = "{$key}: {$value}";
        }
        // Tags fest — hilft in Obsidian bei Sammelabfragen
        $lines[] = 'tags:';
        $lines[] = '  - report';
        $lines[] = '  - verbalizer';
        if (! empty($output->recipe_key)) {
            $lines[] = '  - ' . $this->slugForTag($output->recipe_key);
        }
        $lines[] = '---';
        $lines[] = '';
        return implode("\n", $lines);
    }

    protected function buildFilename(
        VerbalizationChannel $channel,
        VerbalizationFeed $feed,
        VerbalizationOutput $output,
    ): string {
        $config = (array) ($channel->config ?? []);
        $template = (string) ($config['filename_template'] ?? '{date} — {feed_title}.md');

        $date = ($output->created_at ?? now())->format('Y-m-d_H-i');
        $replacements = [
            '{date}' => $date,
            '{feed_title}' => $this->safeFilenamePart((string) $feed->title),
            '{subject_label}' => $this->safeFilenamePart((string) ($output->subject_label ?? '')),
            '{recipe}' => $this->safeFilenamePart((string) ($output->recipe_key ?? '')),
        ];
        $filename = strtr($template, $replacements);
        if (! str_ends_with(strtolower($filename), '.md')) {
            $filename .= '.md';
        }
        return $filename;
    }

    protected function safeYamlValue(string $value): string
    {
        // YAML-sichere Ausgabe: bei problematischen Zeichen quoten.
        if ($value === '') {
            return '""';
        }
        if (preg_match('/[:#\-\'"\[\]{}&*!,>%@`?]/', $value)) {
            $escaped = str_replace('"', '\"', $value);
            return "\"{$escaped}\"";
        }
        return $value;
    }

    protected function safeFilenamePart(string $value): string
    {
        // Nur Zeichen behalten die in Datei-Namen ueberall sicher sind. Umlaute
        // erlauben (Obsidian handhabt sie gut), aber Sonderzeichen ersetzen.
        $value = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    }

    protected function slugForTag(string $value): string
    {
        return Str::slug($value, '_');
    }
}
