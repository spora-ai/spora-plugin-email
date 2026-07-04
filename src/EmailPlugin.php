<?php

declare(strict_types=1);

namespace Spora\Plugins\Email;

use DI\ContainerBuilder;
use Spora\Plugins\AbstractPlugin;
use Spora\Plugins\Email\Imap\ImapClient;
use Spora\Plugins\Email\Imap\ImapClientInterface;
use Spora\Plugins\Email\Tools\EmailTool;

/**
 * Email plugin entry point. Owns the SMTP send + IMAP read stack for Spora
 * agents.
 */
final class EmailPlugin extends AbstractPlugin
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
     * Wire the IMAP dependency. Spora-core's PluginLoader::registerPlugins()
     * invokes this hook once per process during boot, BEFORE the DI container
     * is built. The binding lets php-di autowire `EmailTool` when
     * instantiating it from the `tool_instances` factory.
     */
    public function register(ContainerBuilder $builder): void
    {
        $builder->addDefinitions([
            ImapClientInterface::class => \DI\autowire(ImapClient::class),
        ]);
    }
}
