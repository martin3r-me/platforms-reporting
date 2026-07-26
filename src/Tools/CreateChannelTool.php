<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;

class CreateChannelTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.channels.POST'; }

    public function getDescription(): string
    {
        return 'POST /verbalization/channels - Legt einen Kanal fuer einen Report (Feed) an. Pflicht: feed_id, type. Optional: config (typ-spezifisch), is_active (default true). Beispiel-config obsidian: {"vault_id": 5, "folder": "Berichte/BHG.DIGITAL", "filename_template": "{date} — {feed_title}.md"}.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'feed_id' => ['type' => 'integer'],
                'type' => ['type' => 'string', 'description' => 'Kanal-Typ: rss, obsidian, email, pdf, slack, webhook.'],
                'config' => ['type' => 'object', 'description' => 'Kanal-spezifische Config. Obsidian: {vault_id, folder, filename_template?, include_frontmatter?}.'],
                'is_active' => ['type' => 'boolean'],
            ],
            'required' => ['feed_id', 'type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $feedId = (int) ($arguments['feed_id'] ?? 0);
        $type = trim((string) ($arguments['type'] ?? ''));
        if ($feedId <= 0 || $type === '') {
            return ToolResult::error('VALIDATION_ERROR', 'feed_id und type sind Pflicht.');
        }

        $feed = VerbalizationFeed::find($feedId);
        if (! $feed) {
            return ToolResult::error('FEED_NOT_FOUND', "Feed #{$feedId} nicht gefunden.");
        }

        $channel = VerbalizationChannel::create([
            'verbalization_feed_id' => $feedId,
            'type' => $type,
            'config' => $arguments['config'] ?? null,
            'cadence' => $arguments['cadence'] ?? null,
            'is_active' => (bool) ($arguments['is_active'] ?? true),
        ]);

        return ToolResult::success([
            'id' => $channel->id,
            'uuid' => $channel->uuid,
            'feed_id' => $channel->verbalization_feed_id,
            'type' => $channel->type,
            'config' => $channel->config,
            'is_active' => $channel->is_active,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'channels', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => false,
        ];
    }
}
