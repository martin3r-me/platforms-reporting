<?php

namespace Platform\Reporting\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\VerbalizationChannel;

class DeleteChannelTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string { return 'core.verbalization.channels.DELETE'; }

    public function getDescription(): string
    {
        return 'DELETE /verbalization/channels/{id} - Loescht einen Kanal. Der Report/Feed bleibt bestehen. Bereits ausgelieferte Push-Ausgaben bleiben am Ziel (Obsidian-Dateien werden NICHT geloescht).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm' => ['type' => 'boolean'],
            ],
            'required' => ['id', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }
        if (empty($arguments['confirm'])) {
            return ToolResult::error('CONFIRM_REQUIRED', 'confirm=true erforderlich.');
        }
        $channel = VerbalizationChannel::find((int) ($arguments['id'] ?? 0));
        if (! $channel) {
            return ToolResult::error('CHANNEL_NOT_FOUND', 'Kanal nicht gefunden.');
        }
        $channel->delete();
        return ToolResult::success(['deleted' => true]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'verbalize', 'tags' => ['core', 'verbalization', 'channels', 'delete'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => false,
        ];
    }
}
