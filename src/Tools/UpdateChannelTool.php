<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationChannel;

class UpdateChannelTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.channels.PUT'; }

    public function getDescription(): string
    {
        return 'PUT /verbalization/channels/{id} - Aktualisiert einen Kanal. Nur uebergebene Felder werden geaendert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'config' => ['type' => 'object'],
                'cadence' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }
        $channel = VerbalizationChannel::find((int) ($arguments['id'] ?? 0));
        if (! $channel) {
            return ToolResult::error('CHANNEL_NOT_FOUND', 'Kanal nicht gefunden.');
        }
        foreach (['config', 'cadence', 'is_active'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $channel->{$field} = $arguments[$field];
            }
        }
        $channel->save();

        return ToolResult::success([
            'id' => $channel->id,
            'type' => $channel->type,
            'config' => $channel->config,
            'is_active' => $channel->is_active,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'channels', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
