<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Imap;

interface ImapClientInterface
{
    /**
     * @param array<string, string> $settings
     * @param bool $markAsRead  When true, set the Seen flag on returned messages.
     * @param bool $unreadOnly  When true, return only messages without the Seen flag.
     *                          When false, return the most recent N messages.
     * @return list<array{uid: string, subject: string, from: string, date: string, body: string}>
     */
    public function fetchInboxMessages(array $settings, int $limit, bool $markAsRead, bool $unreadOnly): array;

    /**
     * @param array<string, string> $settings
     * @return list<array{uid: string, subject: string, from: string, date: string, body: string}>
     */
    public function fetchFolderMessages(array $settings, string $folder, int $limit): array;

    /**
     * @param array<string, string> $settings
     * @return list<string>
     */
    public function fetchFolderNames(array $settings): array;

    /**
     * @param array<string, string> $settings
     * @return bool true on success, false on failure.
     */
    public function saveDraft(array $settings, string $to, string $subject, string $body): bool;

    /**
     * @param array<string, string> $settings
     * @return bool True on success, false on failure.
     */
    public function createFolder(array $settings, string $name): bool;

    /**
     * @param array<string, string> $settings
     * @return bool True on success, false on failure.
     */
    public function renameFolder(array $settings, string $oldName, string $newName): bool;

    /**
     * @param array<string, string> $settings
     * @return bool True on success, false on failure.
     */
    public function deleteFolder(array $settings, string $name): bool;

    /**
     * @param array<string, string> $settings
     * @return string The new UID on success, empty string on failure.
     */
    public function moveEmail(array $settings, int $uid, string $fromFolder, string $toFolder): string;

    /**
     * @param array<string, string> $settings
     * @return bool True on success, false on failure.
     */
    public function deleteEmail(array $settings, int $uid, string $folder): bool;

    /**
     * @param array<string, string> $settings
     * @return bool True on success, false on failure.
     */
    public function setEmailFlag(array $settings, int $uid, string $folder, string $flag, bool $enable): bool;
}
