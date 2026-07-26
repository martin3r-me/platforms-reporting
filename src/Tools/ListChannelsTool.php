<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationChannel;

class ListChannelsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.channels.LIST'; }

    public function getDescription(): string
    {
        return 'LIST /verbalization/channels - Listet Kanaele eines Reports (Feeds). Ein Report kann N Kanaele haben (rss, obsidian, email, ...). Optional Filter: feed_id, type.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'feed_id' => ['type' => 'integer', 'description' => 'Nur Kanaele dieses Reports/Feeds.'],
                'type' => ['type' => 'string', 'description' => 'Nur Kanaele dieses Typs (rss, obsidian, email, ...).'],
                'include_inactive' => ['type' => 'boolean', 'description' => 'Default false. Wenn true, auch inaktive Kanaele.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $q = VerbalizationChannel::query()->with('feed:id,uuid,title,team_id');
        if (empty($arguments['include_inactive'])) {
            $q->where('is_active', true);
        }
        if (! empty($arguments['feed_id'])) {
            $q->where('verbalization_feed_id', (int) $arguments['feed_id']);
        }
        if (! empty($arguments['type'])) {
            $q->where('type', (string) $arguments['type']);
        }

        $rows = $q->orderByDesc('id')->get();

        $items = $rows->map(fn (VerbalizationChannel $c) => [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'feed_id' => $c->verbalization_feed_id,
            'feed_title' => $c->feed?->title,
            'type' => $c->type,
            'config' => $c->config,
            'cadence' => $c->cadence,
            'is_active' => $c->is_active,
            'last_delivered_at' => $c->last_delivered_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ])->all();

        return ToolResult::success(['channels' => $items, 'count' => count($items)]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'channels', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
