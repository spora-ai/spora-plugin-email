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
