<?php

declare(strict_types=1);

namespace Spora\Plugins\Email;

use Spora\Plugins\AbstractPlugin;
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
}
