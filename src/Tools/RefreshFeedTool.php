<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Reporting\Verbalization\Feed\FeedService;
use Platform\Core\Verbalization\StyleProfile;

class RefreshFeedTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.feeds.REFRESH'; }

    public function getDescription(): string
    {
        return 'REFRESH /verbalization/feeds/{id} - Triggert die Verbalisierung aller Feed-Subjects (manueller Refresh). Loest Subjects auf, ruft Verbalizer pro Subject, persistiert Outputs. Gibt Anzahl der erzeugten Outputs + Fehler zurueck. Vorsicht: kostet Tokens — pro Subject ~1500-2500. Mit force=true wird die state_hash-Dedup uebergangen (fuer Template/Prompt-Aenderungen ohne State-Delta).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'uuid' => ['type' => 'string'],
                'inject_semantic_layer' => ['type' => 'boolean', 'description' => 'Default true — Semantic Layer aus Team-Kontext in den System-Prompt mergen.'],
                'force' => ['type' => 'boolean', 'description' => 'Default false. Uebergeht die state_hash-Dedup — nutzen wenn sich Template/Prompt geaendert hat, aber der State gleich geblieben ist.'],
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
            return ToolResult::error('FEED_NOT_FOUND', 'Feed nicht gefunden.');
        }

        $injectLayer = $arguments['inject_semantic_layer'] ?? true;
        $style = StyleProfile::formal();
        if ($injectLayer) {
            try {
                $team = $context->team ?? $context->user->currentTeam ?? null;
                /** @var SemanticLayerResolver $resolver */
                $resolver = app(SemanticLayerResolver::class);
                $resolved = $resolver->resolveFor($team, $feed->subject_type ?? 'planner_project');
                if ($resolved->rendered_block) {
                    $style = $style->withSemanticLayer($resolved->rendered_block);
                }
            } catch (\Throwable $e) {
                // optional, Fehler schlucken
            }
        }

        /** @var FeedService $service */
        $service = app(FeedService::class);
        $result = $service->refresh($feed, $style, (bool) ($arguments['force'] ?? false));

        return ToolResult::success(array_merge($result, [
            'feed_id' => $feed->id,
            'feed_uuid' => $feed->uuid,
            'feed_url' => rtrim((string) config('app.url'), '/') . '/feed/' . $feed->uuid . '.xml',
        ]));
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'feeds', 'refresh', 'llm'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => false,
        ];
    }
}
