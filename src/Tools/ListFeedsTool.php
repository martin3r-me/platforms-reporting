<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationFeed;

class ListFeedsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.feeds.LIST'; }

    public function getDescription(): string
    {
        return 'LIST /verbalization/feeds - Listet Verbalization-Feeds des aktuellen Teams + globale Feeds (team_id null). Optional Filter subject_type. Gibt URL, Token, Status, last_refreshed_at zurueck.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'subject_type' => ['type' => 'string'],
                'include_inactive' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }
        $team = $context->team ?? $context->user->currentTeam ?? null;

        $q = VerbalizationFeed::query();
        if (empty($arguments['include_inactive'])) {
            $q->where('is_active', true);
        }
        if (! empty($arguments['subject_type'])) {
            $q->where('subject_type', $arguments['subject_type']);
        }
        $q->where(function ($q) use ($team) {
            $q->whereNull('team_id');
            if ($team) {
                $q->orWhere('team_id', $team->id);
            }
        });

        $rows = $q->orderByDesc('created_at')->get();
        $base = rtrim((string) config('app.url'), '/');

        $items = $rows->map(fn (VerbalizationFeed $f) => [
            'id' => $f->id,
            'uuid' => $f->uuid,
            'slug' => $f->slug,
            'title' => $f->title,
            'description' => $f->description,
            'subject_type' => $f->subject_type,
            'subject_selector' => $f->subject_selector,
            'recipes' => $f->recipes,
            'item_strategy' => $f->item_strategy,
            'access' => $f->access,
            'refresh_strategy' => $f->refresh_strategy,
            'retention_items' => $f->retention_items,
            'is_active' => $f->is_active,
            'team_id' => $f->team_id,
            'scope' => $f->team_id ? 'team' : 'global',
            'last_refreshed_at' => $f->last_refreshed_at?->toIso8601String(),
            'url' => $base . '/feed/' . $f->uuid . '.xml',
        ])->all();

        return ToolResult::success(['feeds' => $items, 'count' => count($items)]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'feeds', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
