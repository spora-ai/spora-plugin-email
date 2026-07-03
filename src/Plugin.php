<?php

declare(strict_types=1);

namespace Spora\Plugins\Email;

use Spora\Plugins\AbstractPlugin;

/**
 * Placeholder plugin entry point for the Email extraction (v0.1.0).
 *
 * The real tool class lands in a follow-up release. This file declares the
 * plugin and an empty hook surface so the framework can install, boot, and
 * inspect it before any tools are available.
 *
 * SMTP send + IMAP read for Spora agents.
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
        return [];
    }
}
