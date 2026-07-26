<?php

namespace Platform\Reporting\Verbalization\Channel;

/**
 * Registry fuer Kanal-Renderer. Jeder Renderer meldet sich mit seinem type()
 * an, damit der Delivery-Loop und der oeffentliche Endpoint anhand der Kanal-
 * Konfiguration den passenden Renderer aufloesen kann.
 */
class ChannelRendererRegistry
{
    /** @var array<string, ChannelRendererInterface> */
    protected array $renderers = [];

    public function register(ChannelRendererInterface $renderer): void
    {
        $this->renderers[$renderer->type()] = $renderer;
    }

    public function resolve(string $type): ?ChannelRendererInterface
    {
        return $this->renderers[$type] ?? null;
    }

    /**
     * @return array<string, ChannelRendererInterface>
     */
    public function all(): array
    {
        return $this->renderers;
    }
}
