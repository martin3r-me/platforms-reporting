<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationFeed;

class GetFeedTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.feeds.GET'; }

    public function getDescription(): string
    {
        return 'GET /verbalization/feeds/{id} - Liefert einen Feed komplett, inkl. URL, Token, Recipes-Map, juengster Outputs.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'uuid' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'include_recent_outputs' => ['type' => 'integer', 'description' => 'Anzahl juengster Outputs (max 20).'],
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
        } elseif (! empty($arguments['slug'])) {
            $feed = VerbalizationFeed::where('slug', $arguments['slug'])->first();
        }
        if (! $feed) {
            return ToolResult::error('FEED_NOT_FOUND', 'Feed nicht gefunden (id, uuid oder slug erforderlich).');
        }

        $base = rtrim((string) config('app.url'), '/');
        $payload = [
            'id' => $feed->id,
            'uuid' => $feed->uuid,
            'slug' => $feed->slug,
            'title' => $feed->title,
            'description' => $feed->description,
            'subject_type' => $feed->subject_type,
            'subject_selector' => $feed->subject_selector,
            'recipes' => $feed->recipes,
            'item_strategy' => $feed->item_strategy,
            'llm_provider' => $feed->llm_provider,
            'llm_model' => $feed->llm_model,
            'access' => $feed->access,
            'refresh_strategy' => $feed->refresh_strategy,
            'retention_items' => $feed->retention_items,
            'is_active' => $feed->is_active,
            'team_id' => $feed->team_id,
            'scope' => $feed->team_id ? 'team' : 'global',
            'last_refreshed_at' => $feed->last_refreshed_at?->toIso8601String(),
            'url' => $base . '/feed/' . $feed->uuid . '.xml',
        ];

        $n = max(0, min(20, (int) ($arguments['include_recent_outputs'] ?? 0)));
        if ($n > 0) {
            $payload['recent_outputs'] = $feed->outputs()->limit($n)->get()->map(fn ($o) => [
                'id' => $o->id, 'uuid' => $o->uuid,
                'subject_type' => $o->subject_type, 'subject_id' => $o->subject_id,
                'subject_label' => $o->subject_label,
                'recipe_key' => $o->recipe_key,
                'created_at' => $o->created_at?->toIso8601String(),
                'tokens' => ['in' => $o->input_tokens, 'out' => $o->output_tokens],
                'prose_preview' => mb_substr((string) $o->prose, 0, 200),
            ])->all();
        }

        return ToolResult::success($payload);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'feeds', 'get'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
