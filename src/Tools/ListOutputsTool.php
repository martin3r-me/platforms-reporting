<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationOutput;

class ListOutputsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.outputs.LIST'; }

    public function getDescription(): string
    {
        return 'LIST /verbalization/outputs - Verlauf der Verbalisierungen. Optional Filter: feed_id, feed_uuid, subject_type, subject_id, since (ISO-Date), limit (default 20, max 100). Sortiert nach created_at desc.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'feed_id' => ['type' => 'integer'],
                'feed_uuid' => ['type' => 'string'],
                'subject_type' => ['type' => 'string'],
                'subject_id' => ['type' => 'string'],
                'since' => ['type' => 'string', 'description' => 'ISO 8601 Datum/Zeit — nur juenger.'],
                'limit' => ['type' => 'integer'],
                'include_prose' => ['type' => 'boolean', 'description' => 'Default false — gibt nur Preview zurueck. Bei true wird die volle Prosa mitgeschickt (Token-kostenrelevant).'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $q = VerbalizationOutput::query();

        if (! empty($arguments['feed_id'])) {
            $q->where('feed_id', (int) $arguments['feed_id']);
        } elseif (! empty($arguments['feed_uuid'])) {
            $feedId = \DB::table('verbalization_feeds')->where('uuid', $arguments['feed_uuid'])->value('id');
            if (! $feedId) {
                return ToolResult::error('FEED_NOT_FOUND', 'feed_uuid nicht gefunden.');
            }
            $q->where('feed_id', $feedId);
        }

        if (! empty($arguments['subject_type'])) {
            $q->where('subject_type', $arguments['subject_type']);
        }
        if (! empty($arguments['subject_id'])) {
            $q->where('subject_id', (string) $arguments['subject_id']);
        }
        if (! empty($arguments['since'])) {
            try {
                $since = new \DateTimeImmutable($arguments['since']);
                $q->where('created_at', '>=', $since);
            } catch (\Throwable $e) {
                return ToolResult::error('VALIDATION_ERROR', 'since: ungueltiges Datum/Zeit-Format. ISO 8601 erwartet.');
            }
        }

        $limit = max(1, min(100, (int) ($arguments['limit'] ?? 20)));
        $includeProse = (bool) ($arguments['include_prose'] ?? false);

        $rows = $q->orderByDesc('created_at')->limit($limit)->get();

        $items = $rows->map(function ($o) use ($includeProse) {
            $base = [
                'id' => $o->id, 'uuid' => $o->uuid, 'feed_id' => $o->feed_id,
                'subject_type' => $o->subject_type, 'subject_id' => $o->subject_id,
                'subject_label' => $o->subject_label,
                'recipe_key' => $o->recipe_key,
                'created_at' => $o->created_at?->toIso8601String(),
                'tokens' => ['in' => $o->input_tokens, 'out' => $o->output_tokens],
                'model' => $o->llm_model,
                'provider' => $o->llm_provider,
            ];
            if ($includeProse) {
                $base['prose'] = $o->prose;
            } else {
                $base['prose_preview'] = mb_substr((string) $o->prose, 0, 240);
            }
            return $base;
        })->all();

        return ToolResult::success(['outputs' => $items, 'count' => count($items)]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'outputs', 'list', 'history'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
