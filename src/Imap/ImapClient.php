<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Imap;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;

/**
 * Real IMAP client using webklex/php-imap.
 */
final class ImapClient implements ImapClientInterface
{
    /**
     * Common drafts-folder names used as a last-resort fallback after the
     * RFC 6154 `\Drafts` special-use flag lookup. Compared case-insensitively
     * against each folder's simple name (last path segment after the
     * delimiter) so Gmail's `[Gmail]/Drafts`, Dovecot's `Drafts`, and
     * Yahoo's `Draft` are all reached.
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

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    private function connect(array $settings): ?Client
    {
        $host    = $settings['host'] ?? '';
        $port    = $settings['port'] ?? '993';
        $enc     = $settings['encryption'] ?? 'ssl';
        $user    = $settings['username'] ?? '';
        $pass    = $settings['password'] ?? '';
        $timeout = (int) ($settings['timeout'] ?? 60);

        if (empty($host) || empty($user) || empty($pass)) {
            return null;
        }

        $cm = new ClientManager();
        $client = $cm->make([
            'host'          => $host,
            'port'          => (int) $port,
            'encryption'    => $enc,
            'validate_cert' => true,
            'username'      => $user,
            'password'      => $pass,
            'protocol'      => 'imap',
            'timeout'       => $timeout,
        ]);

        $client->connect();
        return $client;
    }

    public function fetchInboxMessages(array $settings, int $limit, bool $markAsRead, bool $unreadOnly): array
    {
        if ($limit <= 0 || $limit > 20) {
            $limit = 5;
        }

        return $this->fetchMessages('INBOX', $settings, $limit, $markAsRead, $unreadOnly);
    }

    public function fetchFolderMessages(array $settings, string $folder, int $limit): array
    {
        if ($limit <= 0 || $limit > 20) {
            $limit = 5;
        }

        return $this->fetchMessages($folder, $settings, $limit, false);
    }

    public function fetchFolderNames(array $settings): array
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return [];
            }

            $folders = $client->getFolders();
            $names = array_map(fn($f) => $f->name, $folders->all());
            sort($names);
            $client->disconnect();

            return $names;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP list folders error', ['exception' => $e]);
            throw $e;
        }
    }

    public function saveDraft(array $settings, string $to, string $subject, string $body): bool
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return false;
            }

            $draftFolder = $this->resolveDraftsFolder($client, $settings);
            if ($draftFolder === null) {
                $this->logger?->error('IMAP save draft error: could not locate a drafts folder. Set imap_drafts_folder to pin a specific path.');
                $client->disconnect();
                return false;
            }

            $from = $settings['from'] ?? ($settings['username'] ?? '');

            $email = (new Email())
                ->from($from)
                ->to($to)
                ->subject($subject)
                ->text($body);

            $draftFolder->appendMessage($email->toString());
            $client->disconnect();

            return true;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP save draft error', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Locate the drafts folder on the connected IMAP mailbox.
     *
     * Resolution order:
     *   1. `$settings['drafts_folder']` operator override (used verbatim via
     *      `Client::getFolderByPath()`; returns null when the path doesn't
     *      exist on the server).
     *   2. RFC 6154 `\Drafts` special-use flag from the raw LIST response.
     *      webklex/php-imap's `Folder` model drops the special-use flag, so
     *      we read the flags directly from `Client::getConnection()->folders()`
     *      and pick the first match by path.
     *   3. Common folder-name aliases matched against each folder's simple
     *      name (last path segment), case-insensitively.
     */
    private function resolveDraftsFolder(Client $client, array $settings): ?Folder
    {
        $override = trim((string) ($settings['drafts_folder'] ?? ''));
        if ($override !== '') {
            return $client->getFolderByPath($override, true, true);
        }

        $byFlag = $this->findDraftsFolderBySpecialUseFlag($client);
        if ($byFlag !== null) {
            return $byFlag;
        }

        return $this->findDraftsFolderByAlias($client);
    }

    /**
     * Scan the raw LIST response for a folder flagged with `\Drafts`. The
     * webklex/php-imap `Folder` constructor receives the flags but discards
     * the special-use ones in `parseAttributes()`, so we have to bypass the
     * model and read the protocol-level payload directly.
     */
    private function findDraftsFolderBySpecialUseFlag(Client $client): ?Folder
    {
        try {
            $raw = $client->getConnection()->folders('', '*')->validatedData();
        } catch (Throwable $e) {
            $this->logger?->warning('IMAP LIST for special-use failed', ['exception' => $e]);
            return null;
        }
        if (!is_array($raw)) {
            return null;
        }

        $path = self::pickDraftsPathFromRawList($raw);
        if ($path === null) {
            return null;
        }

        return $client->getFolderByPath($path, true, true);
    }

    /**
     * Pure helper: scan a raw LIST payload (path => ['flags' => [...]])
     * for the first folder whose flags include `\Drafts`, and return its
     * path. Returns null when no match is found.
     *
     * @param array<int|string, mixed> $raw
     */
    private static function pickDraftsPathFromRawList(array $raw): ?string
    {
        foreach ($raw as $path => $item) {
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
     * Last-resort name lookup. Iterates `Client::getFolders()` and matches
     * each folder's `name` (last path segment after the delimiter)
     * case-insensitively against {@see DRAFT_FOLDER_ALIASES}. The first
     * match wins; later matches are ignored even if they would also satisfy
     * the alias check.
     */
    private function findDraftsFolderByAlias(Client $client): ?Folder
    {
        try {
            $folders = $client->getFolders(false, null, true);
        } catch (Throwable $e) {
            $this->logger?->warning('IMAP LIST for alias match failed', ['exception' => $e]);
            return null;
        }

        $names = [];
        foreach ($folders as $folder) {
            $names[] = (string) $folder->name;
        }

        $name = self::pickDraftsNameFromAliases($names);
        if ($name === null) {
            return null;
        }

        foreach ($folders as $folder) {
            if ((string) $folder->name === $name) {
                return $folder;
            }
        }

        return null;
    }

    /**
     * Pure helper: return the first folder simple name that matches one of
     * the {@see DRAFT_FOLDER_ALIASES} case-insensitively, or null on no
     * match.
     *
     * @param list<string> $names
     */
    private static function pickDraftsNameFromAliases(array $names): ?string
    {
        $aliasesLower = array_map('strtolower', self::DRAFT_FOLDER_ALIASES);
        foreach ($names as $name) {
            if (in_array(strtolower($name), $aliasesLower, true)) {
                return $name;
            }
        }
        return null;
    }

    public function createFolder(array $settings, string $name): bool
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return false;
            }

            // Passing false to avoid the library's default behavior of calling expunge()
            // after folder operations, which fails if no mailbox is selected.
            $client->createFolder($name, false);
            $client->disconnect();

            return true;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP create folder error', ['folder' => $name, 'exception' => $e]);
            return false;
        }
    }

    public function renameFolder(array $settings, string $oldName, string $newName): bool
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return false;
            }

            // Passing false to avoid the library's default behavior of calling expunge()
            $client->getFolder($oldName)->rename($newName, false);
            $client->disconnect();

            return true;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP rename folder error', ['old' => $oldName, 'new' => $newName, 'exception' => $e]);
            return false;
        }
    }

    public function deleteFolder(array $settings, string $name): bool
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return false;
            }

            // Passing false to avoid the library's default behavior of calling expunge()
            $client->deleteFolder($name, false);
            $client->disconnect();

            return true;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP delete folder error', ['folder' => $name, 'exception' => $e]);
            return false;
        }
    }

    public function moveEmail(array $settings, int $uid, string $fromFolder, string $toFolder): string
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return '';
            }

            $movedMessage = $client->getFolder($fromFolder)->messages()->getMessageByUid($uid)->move($toFolder);
            $newUid = $movedMessage?->getUid() ?? '';
            $client->disconnect();

            return (string) $newUid;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP move email error', ['uid' => $uid, 'from' => $fromFolder, 'to' => $toFolder, 'exception' => $e]);
            return '';
        }
    }

    public function deleteEmail(array $settings, int $uid, string $folder): bool
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return false;
            }

            $client->getFolder($folder)->messages()->getMessageByUid($uid)->delete(true);
            $client->disconnect();

            return true;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP delete email error', ['uid' => $uid, 'folder' => $folder, 'exception' => $e]);
            return false;
        }
    }

    public function setEmailFlag(array $settings, int $uid, string $folder, string $flag, bool $enable): bool
    {
        try {
            $client = $this->connect($settings);
            if (!$client) {
                return false;
            }

            $message = $client->getFolder($folder)->messages()->getMessageByUid($uid);
            if ($enable) {
                $message->setFlag($flag);
            } else {
                $message->unsetFlag($flag);
            }
            $client->disconnect();

            return true;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP set email flag error', ['uid' => $uid, 'folder' => $folder, 'flag' => $flag, 'enable' => $enable, 'exception' => $e]);
            return false;
        }
    }

    /**
     * @param array<string, string> $settings
     * @return list<array{uid: string, subject: string, from: string, date: string, body: string}>
     */
    private function fetchMessages(string $folder, array $settings, int $limit, bool $markAsRead, bool $unreadOnly = false): array
    {
        try {
            $client = $this->connect($settings);
            if ($client === null) {
                return [];
            }

            $results = $this->collectMessages($client, $folder, $limit, $markAsRead, $unreadOnly);
            $client->disconnect();
            return $results;
        } catch (Throwable $e) {
            $this->logger?->error('IMAP fetch messages error', ['folder' => $folder, 'exception' => $e]);
            return [];
        }
    }

    /**
     * @return list<array{uid: string, subject: string, from: string, date: string, body: string}>
     */
    private function collectMessages(Client $client, string $folder, int $limit, bool $markAsRead, bool $unreadOnly): array
    {
        $mailFolder = $client->getFolder($folder);

        $query = $mailFolder->messages()->all()->fetchOrderDesc();
        if ($unreadOnly) {
            $query = $query->unseen();
        }

        $messages = $query->limit($limit)->get();
        if ($messages->isEmpty()) {
            return [];
        }

        $results = [];
        foreach ($messages as $message) {
            $results[] = $this->mapMessage($message);
            if ($markAsRead) {
                $message->setFlag('Seen');
            }
        }

        return $results;
    }

    /**
     * Map a webklex message object into the normalized summary shape.
     *
     * @return array{uid: string, subject: string, from: string, date: string, body: string}
     */
    private function mapMessage(object $message): array
    {
        $headers = MessageParser::parseHeaders([
            'from'    => $message->getFrom(),
            'to'      => $message->getTo(),
            'cc'      => $message->getCc(),
            'bcc'     => $message->getBcc(),
            'subject' => $message->getSubject(),
            'date'    => $message->getDate()?->toDate()?->format('Y-m-d H:i:s') ?? 'Unknown Date',
        ]);

        $fromAddresses = MessageParser::parseAddressList((string) ($headers['from'] ?? ''));
        $from = $fromAddresses[0]['email'] ?? 'Unknown';

        $text = (string) $message->getTextBody();
        $html = (string) ($message->getHTMLBody() ?? '');
        $body = $text !== '' ? $text : strip_tags($html);

        return [
            'uid'     => (string) $message->getUid(),
            'subject' => (string) $message->getSubject(),
            'from'    => $from,
            'date'    => $headers['date'],
            'body'    => $body,
        ];
    }
}
