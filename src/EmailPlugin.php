<?php

declare(strict_types=1);

namespace Spora\Plugins\Email;

use Spora\Events\ContainerBuildingEvent;
use Spora\Plugins\AbstractPlugin;
use Spora\Plugins\Email\Imap\ImapClient;
use Spora\Plugins\Email\Imap\ImapClientInterface;
use Spora\Plugins\Email\Tools\EmailTool;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Email plugin entry point. Owns the SMTP send + IMAP read stack for Spora
 * agents.
 */
final class EmailPlugin extends AbstractPlugin implements EventSubscriberInterface
{
    public function getName(): string
    {
        return 'Email';
    }

    /** @return array<class-string<\Spora\Tools\ToolInterface>> */
    public function tools(): array
    {
        return [EmailTool::class];
    }

    /**
     * Translation catalogues this plugin ships, declared through
     * {@see \Spora\Plugins\PluginInterface::translationsPaths()}.
     *
     * Keys are the English source text — `de.json` reads
     * `{"Email": "E-Mail"}`, not `{"name": "E-Mail"}`. That is what makes the
     * host's `i18n:check` able to fail when a string is edited without being
     * re-translated: the key changes and the old entry orphans.
     *
     * **Only operator copy belongs in here.** A string the model reads is
     * never translated, which for this plugin means `#[Tool].description`,
     * every `#[ToolOperation].description`, every `#[ToolParameter].description`,
     * every `#[ToolSetting].label` and its `options`, and all `ToolResult`
     * content. What `locales/de.json` holds is the operator-facing copy:
     * every tool's `displayName`, every `#[ToolSetting].description`, the
     * `plugin.json` description, and the `describeAction()` strings — each
     * a string only an operator ever reads.
     *
     * Needs a core that reads `translationsPaths()` — 0.31.0 and later. On
     * 0.30.x nothing calls the method, so the catalogue is silently
     * ignored and the copy stays English, which is the fallback every
     * layer already uses.
     *
     * @return string[]
     */
    public function translationsPaths(): array
    {
        return [
            __DIR__ . '/../locales',
        ];
    }

    /**
     * Listens for {@see ContainerBuildingEvent} to register the IMAP
     * dependency so php-di can autowire `EmailTool` when the host App
     * instantiates it from the `tool_instances` factory.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ContainerBuildingEvent::class => 'onContainerBuilding',
        ];
    }

    public function onContainerBuilding(ContainerBuildingEvent $event): void
    {
        $event->builder()->addDefinitions([
            ImapClientInterface::class => \DI\autowire(ImapClient::class),
        ]);
    }
}
