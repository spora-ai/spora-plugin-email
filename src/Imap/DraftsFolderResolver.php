<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Imap;

/**
 * Three-tier drafts-folder priority:
 *   1. operator `imap_drafts_folder` override
 *   2. RFC 6154 `\Drafts` special-use flag from the raw LIST response
 *   3. name alias fallback
 *
 * Pulled out of {@see ImapClient} so the decision logic is unit-testable
 * without a live IMAP connection. The IO layer gathers `rawList` and
 * `folderNames`; everything in here is a pure function of those inputs.
 */
final class DraftsFolderResolver
{
    /**
     * Covers Dovecot (`Drafts`), Yahoo (`Draft`), German providers, and
     * Gmail (`[Gmail]/Drafts`).
     */
    private const DRAFT_FOLDER_ALIASES = [
        'Drafts',
        'Draft',
        'INBOX/Drafts',
        'INBOX.Drafts',
        '[Gmail]/Drafts',
        '[Google Mail]/Drafts',
    ];

    private const SPECIAL_USE_DRAFTS = '\Drafts';

    /**
     * @param array<int|string, mixed> $rawList      Pass `[]` when LIST failed.
     * @param list<string>             $folderNames  Pass `[]` when LIST failed.
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
