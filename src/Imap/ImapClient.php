<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Imap;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

/**
 * Real IMAP client using webklex/php-imap.
 *
 * Not `final` so tests can subclass it with a `connect()` override.
 */
class ImapClient implements ImapClientInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    protected function connect(array $settings): ?Client
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
            return $client !== null && $this->appendDraft($client, $settings, $to, $subject, $body);
        } catch (Throwable $e) {
            $this->logger?->error('IMAP save draft error', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Build and APPEND the draft message to the resolved drafts folder.
     * Split from {@see saveDraft()} so each method stays under Sonar's
     * S1142 (max 3 returns) cap.
     */
    private function appendDraft(Client $client, array $settings, string $to, string $subject, string $body): bool
    {
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
    }

    /**
     * Delegate the priority-chain decision to {@see DraftsFolderResolver};
     * this method only does IO (LIST, getFolderByPath). The webklex/php-imap
     * `Folder` constructor discards the RFC 6154 `\Drafts` flag in
     * `parseAttributes()`, so we read the raw LIST payload directly via
     * `Client::getConnection()->folders()`.
     */
    private function resolveDraftsFolder(Client $client, array $settings): mixed
    {
        $rawList = [];
        try {
            $rawList = $client->getConnection()->folders('', '*')->validatedData();
        } catch (Throwable $e) {
            $this->logger?->warning('IMAP LIST for special-use failed', ['exception' => $e]);
        }

        $names = [];
        try {
            foreach ($client->getFolders(false, null, true) as $folder) {
                $names[] = $folder->name;
            }
        } catch (Throwable $e) {
            $this->logger?->warning('IMAP LIST for alias match failed', ['exception' => $e]);
        }

        $path = (new DraftsFolderResolver())->resolve(
            (string) ($settings['drafts_folder'] ?? ''),
            is_array($rawList) ? $rawList : [],
            $names,
        );
        if ($path === null) {
            return null;
        }

        return $client->getFolderByPath($path, true, true);
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
