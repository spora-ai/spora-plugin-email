<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Imap;

/**
 * Pure orchestration of the drafts-folder resolution chain.
 *
 * Three-tier priority, in order:
 *   1. operator-supplied `imap_drafts_folder` override (used verbatim)
 *   2. RFC 6154 `\Drafts` special-use flag from the raw LIST response
 *   3. common folder-name aliases matched case-insensitively
 *
 * Pulled out of {@see ImapClient} so the decision
 * logic can be unit-tested without a live IMAP connection. The IO layer
 * gathers `rawList` and `folderNames` from the server; everything in here
 * is a pure function of those inputs.
 */
final class DraftsFolderResolver
{
    /**
     * Common drafts-folder names used as a last-resort fallback after the
     * RFC 6154 `\Drafts` special-use flag lookup. Covers Dovecot (`Drafts`),
     * Yahoo (`Draft`), German providers, and Gmail (`[Gmail]/Drafts`).
     */
    private const DRAFT_FOLDER_ALIASES = [
        'Drafts',
        'Draft',
        'INBOX/Drafts',
        'INBOX.Drafts',
        '[Gmail]/Drafts',
        '[Google Mail]/Drafts',
    ];

    /**
     * RFC 6154 special-use flag identifying a drafts mailbox.
     */
    private const SPECIAL_USE_DRAFTS = '\Drafts';

    /**
     * Resolve the drafts-folder path per the priority chain. Returns null
     * when no candidate matches.
     *
     * @param array<int|string, mixed> $rawList      Raw LIST payload (path => ['flags' => [...]]); pass `[]` if LIST failed.
     * @param list<string>             $folderNames  Simple folder names (last path segment after delimiter); pass `[]` if LIST failed.
     */
    public function resolve(string $override, array $rawList, array $folderNames): ?string
    {
        $trimmed = trim($override);
        if ($trimmed !== '') {
            return $trimmed;
        }

        return self::pickBySpecialUseFlag($rawList) ?? self::pickByAlias($folderNames);
    }

    /**
     * First folder in `rawList` whose flags include `\Drafts`, or null.
     *
     * @param array<int|string, mixed> $rawList
     */
    public static function pickBySpecialUseFlag(array $rawList): ?string
    {
        foreach ($rawList as $path => $item) {
            if (!is_array($item) || !isset($item['flags']) || !is_array($item['flags'])) {
                continue;
            }
            if (in_array(self::SPECIAL_USE_DRAFTS, $item['flags'], true)) {
                return (string) $path;
            }
        }
        return null;
    }

    /**
     * @param list<string> $folderNames
     */
    public static function pickByAlias(array $folderNames): ?string
    {
        $aliasesLower = array_map('strtolower', self::DRAFT_FOLDER_ALIASES);
        foreach ($folderNames as $name) {
            if (in_array(strtolower($name), $aliasesLower, true)) {
                return $name;
            }
        }
        return null;
    }
}
