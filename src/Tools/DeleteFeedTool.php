<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationFeed;

class DeleteFeedTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.feeds.DELETE'; }

    public function getDescription(): string
    {
        return 'DELETE /verbalization/feeds/{id} - Loescht einen Feed inkl. seiner Outputs (cascade). confirm=true Pflicht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'uuid' => ['type' => 'string'],
                'confirm' => ['type' => 'boolean'],
            ],
            'required' => ['confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }
        if (empty($arguments['confirm'])) {
            return ToolResult::error('VALIDATION_ERROR', 'confirm=true ist Pflicht.');
        }

        $feed = null;
        if (! empty($arguments['id'])) {
            $feed = VerbalizationFeed::find((int) $arguments['id']);
        } elseif (! empty($arguments['uuid'])) {
            $feed = VerbalizationFeed::where('uuid', $arguments['uuid'])->first();
        }
        if (! $feed) {
            return ToolResult::error('FEED_NOT_FOUND', 'Feed nicht gefunden.');
        }

        $info = ['id' => $feed->id, 'uuid' => $feed->uuid, 'title' => $feed->title];
        $feed->delete();

        return ToolResult::success(['deleted' => $info]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'feeds', 'delete'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'destructive', 'idempotent' => true,
        ];
    }
}
