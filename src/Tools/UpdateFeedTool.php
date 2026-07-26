<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationFeed;

class UpdateFeedTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.feeds.PUT'; }

    public function getDescription(): string
    {
        return 'PUT /verbalization/feeds/{id} - Aktualisiert einen Feed (id oder uuid). Nur uebergebene Felder werden geaendert. rotate_token=true erzeugt neue UUID — alte URL wird ungueltig.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'uuid' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'subject_selector' => ['type' => 'object'],
                'recipes' => ['type' => 'object'],
                'item_strategy' => ['type' => 'string', 'enum' => ['history', 'snapshot']],
                'llm_provider' => ['type' => 'string'],
                'llm_model' => ['type' => 'string'],
                'access' => ['type' => 'string', 'enum' => ['public', 'team']],
                'refresh_strategy' => ['type' => 'string', 'enum' => ['on_request', 'cron_daily', 'cron_weekly']],
                'retention_items' => ['type' => 'integer'],
                'is_active' => ['type' => 'boolean'],
                'rotate_token' => ['type' => 'boolean', 'description' => 'Neue UUID erzeugen — alter Token wird ungueltig.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $feed = null;
        if (! empty($arguments['id'])) {
            $feed = VerbalizationFeed::find((int) $arguments['id']);
        } elseif (! empty($arguments['uuid'])) {
            $feed = VerbalizationFeed::where('uuid', $arguments['uuid'])->first();
        }
        if (! $feed) {
            return ToolResult::error('FEED_NOT_FOUND', 'Feed nicht gefunden (id oder uuid erforderlich).');
        }

        foreach (['title', 'slug', 'description', 'subject_selector', 'recipes', 'item_strategy',
                  'llm_provider', 'llm_model', 'access', 'refresh_strategy',
                  'retention_items', 'is_active'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $feed->{$field} = $arguments[$field];
            }
        }

        if (! empty($arguments['rotate_token'])) {
            $feed->rotateToken();
        }

        $feed->save();

        $base = rtrim((string) config('app.url'), '/');

        return ToolResult::success([
            'id' => $feed->id,
            'uuid' => $feed->uuid,
            'url' => $base . '/feed/' . $feed->uuid . '.xml',
            'token_rotated' => ! empty($arguments['rotate_token']),
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'feeds', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
