<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Email;

use Spora\Plugins\Email\Imap\ImapClientInterface;

/**
 * Carries the three collaborators needed for IMAP folder invariant checks.
 * Introduced so the folder guards stay inside SonarQube's S107
 * parameter-count cap.
 *
 * Immutable by convention; readonly props prevent accidental mutation.
 */
final class FolderCheckContext
{
    public function __construct(
        public readonly EmailSettingsResolver $resolver,
        public readonly ImapClientInterface $imap,
        public readonly EmailMessageFormatter $formatter,
    ) {}
}
