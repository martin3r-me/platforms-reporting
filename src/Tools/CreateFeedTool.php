<?php

namespace Platform\Reporting\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationFeed;

class CreateFeedTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.feeds.POST'; }

    public function getDescription(): string
    {
        return 'POST /verbalization/feeds - Legt einen Verbalization-Feed an. Pflicht: title, subject_selector (mode: single|list|entity), recipes (Map subject_type -> recipe_key). Optional: subject_type, slug, description, item_strategy (history|snapshot, default history), refresh_strategy (on_request|cron_daily|cron_weekly, default on_request), access (public|team, default public), retention_items (default 50), llm_provider, llm_model, scope (global|team, default team). Beispiele: subject_selector={"mode":"single","id":53} oder {"mode":"entity","entity_id":137}. recipes={"planner_project":"customer_brief","helpdesk_board":"customer_brief"}.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'subject_type' => ['type' => 'string', 'description' => 'Bei mixed-type-Feeds optional — recipes-Map definiert dann pro subject_type.'],
                'subject_selector' => ['type' => 'object'],
                'recipes' => ['type' => 'object'],
                'item_strategy' => ['type' => 'string', 'enum' => ['history', 'snapshot']],
                'llm_provider' => ['type' => 'string'],
                'llm_model' => ['type' => 'string'],
                'access' => ['type' => 'string', 'enum' => ['public', 'team']],
                'refresh_strategy' => ['type' => 'string', 'enum' => ['on_request', 'cron_daily', 'cron_weekly']],
                'retention_items' => ['type' => 'integer'],
                'scope' => ['type' => 'string', 'enum' => ['global', 'team']],
                'is_active' => ['type' => 'boolean'],
            ],
            'required' => ['title', 'subject_selector', 'recipes'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $title = trim((string) ($arguments['title'] ?? ''));
        $selector = $arguments['subject_selector'] ?? null;
        $recipes = $arguments['recipes'] ?? null;
        if ($title === '' || ! is_array($selector) || ! is_array($recipes) || empty($recipes)) {
            return ToolResult::error('VALIDATION_ERROR', 'title, subject_selector (object), recipes (non-empty object) sind Pflicht.');
        }

        $mode = $selector['mode'] ?? null;
        if (! in_array($mode, ['single', 'list', 'entity'], true)) {
            return ToolResult::error('VALIDATION_ERROR', 'subject_selector.mode muss "single", "list" oder "entity" sein.');
        }

        $scope = $arguments['scope'] ?? 'team';
        $team = $context->team ?? $context->user->currentTeam ?? null;
        $teamId = $scope === 'global' ? null : $team?->id;
        if ($scope === 'team' && ! $teamId) {
            return ToolResult::error('VALIDATION_ERROR', 'scope=team aber kein Team-Kontext verfuegbar.');
        }

        $feed = VerbalizationFeed::create([
            'title' => $title,
            'slug' => $arguments['slug'] ?? null,
            'description' => $arguments['description'] ?? null,
            'subject_type' => $arguments['subject_type'] ?? (count($recipes) === 1 ? array_key_first($recipes) : null),
            'subject_selector' => $selector,
            'recipes' => $recipes,
            'item_strategy' => $arguments['item_strategy'] ?? 'history',
            'llm_provider' => $arguments['llm_provider'] ?? null,
            'llm_model' => $arguments['llm_model'] ?? null,
            'access' => $arguments['access'] ?? 'public',
            'refresh_strategy' => $arguments['refresh_strategy'] ?? 'on_request',
            'retention_items' => $arguments['retention_items'] ?? 50,
            'is_active' => $arguments['is_active'] ?? true,
            'team_id' => $teamId,
            'owner_user_id' => Auth::id() ?? $context->user->id ?? null,
        ]);

        $base = rtrim((string) config('app.url'), '/');

        return ToolResult::success([
            'id' => $feed->id,
            'uuid' => $feed->uuid,
            'url' => $base . '/feed/' . $feed->uuid . '.xml',
            'scope' => $teamId ? 'team' : 'global',
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'feeds', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => false,
        ];
    }
}
