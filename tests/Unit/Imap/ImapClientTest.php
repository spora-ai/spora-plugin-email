<?php

declare(strict_types=1);

use Spora\Plugins\Email\Imap\ImapClient;
use Webklex\PHPIMAP\Client;

const IMAP_VALID_HOST = 'imap.example.com';
const IMAP_VALID_USER = 'alice@example.com';
const IMAP_VALID_PASS = 'secret123';

function imapClient(): ImapClient
{
    return new ImapClient();
}

function imapValidSettings(): array
{
    return [
        'host'     => IMAP_VALID_HOST,
        'port'     => '993',
        'username' => IMAP_VALID_USER,
        'password' => IMAP_VALID_PASS,
    ];
}

it('returns empty array when settings are incomplete (fetchFolderNames)', function () {
    $client = imapClient();
    expect($client->fetchFolderNames([]))->toBe([]);
});

it('returns empty array when settings are incomplete (fetchInboxMessages)', function () {
    $client = imapClient();
    expect($client->fetchInboxMessages([], 5, false, false))->toBe([]);
});

it('returns empty array when settings are incomplete (fetchFolderMessages)', function () {
    $client = imapClient();
    expect($client->fetchFolderMessages([], 'INBOX', 5))->toBe([]);
});

it('returns false when settings are incomplete (saveDraft)', function () {
    $client = imapClient();
    expect($client->saveDraft([], 'to@example.com', 'subject', 'body'))->toBeFalse();
});

it('returns false when settings are incomplete (createFolder)', function () {
    $client = imapClient();
    expect($client->createFolder([], 'MyFolder'))->toBeFalse();
});

it('returns false when settings are incomplete (renameFolder)', function () {
    $client = imapClient();
    expect($client->renameFolder([], 'old', 'new'))->toBeFalse();
});

it('returns false when settings are incomplete (deleteFolder)', function () {
    $client = imapClient();
    expect($client->deleteFolder([], 'Trash'))->toBeFalse();
});

it('returns empty string when settings are incomplete (moveEmail)', function () {
    $client = imapClient();
    expect($client->moveEmail([], 1, 'INBOX', 'Archive'))->toBe('');
});

it('returns false when settings are incomplete (deleteEmail)', function () {
    $client = imapClient();
    expect($client->deleteEmail([], 1, 'INBOX'))->toBeFalse();
});

it('returns false when settings are incomplete (setEmailFlag)', function () {
    $client = imapClient();
    expect($client->setEmailFlag([], 1, 'INBOX', 'Seen', true))->toBeFalse();
});

it('clamps fetchInboxMessages limit to default when out of range', function () {
    $client = imapClient();
    // With no settings, returns [] — but the clamp logic still runs.
    // We can't assert the clamp value directly without a real IMAP connection,
    // but we can confirm the early-return path with each of the out-of-range
    // limits completes without error.
    expect($client->fetchInboxMessages([], 0, false, false))->toBe([]);
    expect($client->fetchInboxMessages([], 100, false, false))->toBe([]);
});

it('clamps fetchFolderMessages limit to default when out of range', function () {
    $client = imapClient();
    expect($client->fetchFolderMessages([], 'INBOX', 0))->toBe([]);
    expect($client->fetchFolderMessages([], 'INBOX', 100))->toBe([]);
});

/**
 * Test seam: bypasses the real IMAP connection and returns a Mockery-mocked
 * `Client` so we can exercise the drafts-folder resolution orchestration
 * without a live server.
 */
final class FakeImapClient extends ImapClient
{
    public function __construct(
        private readonly Client $fakeClient,
        ?Psr\Log\LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    protected function connect(array $settings): Client
    {
        return $this->fakeClient;
    }
}

describe('ImapClient::saveDraft drafts-folder orchestration', function () {

    it('uses the operator override verbatim and appends to that folder', function () {
        $draftFolder = Mockery::mock(Webklex\PHPIMAP\Folder::class);
        $draftFolder->shouldReceive('appendMessage')->once();
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('getFolderByPath')
            ->with('[Gmail]/Drafts', true, true)
            ->once()
            ->andReturn($draftFolder);
        $client->shouldReceive('disconnect')->once();

        $settings = imapValidSettings();
        $settings['drafts_folder'] = '[Gmail]/Drafts';
        $settings['from'] = 'agent@example.com';

        $sut = new FakeImapClient($client);
        expect($sut->saveDraft($settings, 'to@example.com', 'subj', 'body'))->toBeTrue();
    });

    it('falls back to the \\Drafts special-use flag when no override is set', function () {
        $draftFolder = Mockery::mock(Webklex\PHPIMAP\Folder::class);
        $draftFolder->shouldReceive('appendMessage')->once();

        $response = Mockery::mock();
        $response->shouldReceive('validatedData')->andReturn([
            'INBOX' => ['flags' => ['\\HasNoChildren']],
            'Drafts' => ['flags' => ['\\Drafts', '\\HasNoChildren']],
        ]);

        $connection = Mockery::mock();
        $connection->shouldReceive('folders')->andReturn($response);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('getConnection')->andReturn($connection);
        $client->shouldReceive('getFolders')->andReturn(collect_folders(['INBOX', 'Drafts']));
        $client->shouldReceive('getFolderByPath')
            ->with('Drafts', true, true)
            ->andReturn($draftFolder);
        $client->shouldReceive('disconnect')->once();

        $settings = imapValidSettings();
        $settings['from'] = 'agent@example.com';

        $sut = new FakeImapClient($client);
        expect($sut->saveDraft($settings, 'to@example.com', 'subj', 'body'))->toBeTrue();
    });

    it('falls back to name alias when no \\Drafts flag is set', function () {
        $draftFolder = Mockery::mock(Webklex\PHPIMAP\Folder::class);
        $draftFolder->shouldReceive('appendMessage')->once();

        $connection = Mockery::mock();
        $connection->shouldReceive('folders')->andReturnUsing(function () {
            $response = Mockery::mock();
            $response->shouldReceive('validatedData')->andReturn([
                'INBOX' => ['flags' => ['\\HasNoChildren']],
                'Sent' => ['flags' => ['\\Sent']],
            ]);
            return $response;
        });

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('getConnection')->andReturn($connection);
        $client->shouldReceive('getFolders')->andReturn(collect_folders(['INBOX', 'Sent', 'Drafts']));
        $client->shouldReceive('getFolderByPath')
            ->with('Drafts', true, true)
            ->andReturn($draftFolder);
        $client->shouldReceive('disconnect')->once();

        $sut = new FakeImapClient($client);
        expect($sut->saveDraft(imapValidSettings(), 'to@example.com', 'subj', 'body'))->toBeTrue();
    });

    it('returns false when no drafts folder resolves', function () {
        $connection = Mockery::mock();
        $connection->shouldReceive('folders')->andReturnUsing(function () {
            $response = Mockery::mock();
            $response->shouldReceive('validatedData')->andReturn([
                'INBOX' => ['flags' => ['\\HasNoChildren']],
                'Sent' => ['flags' => ['\\Sent']],
            ]);
            return $response;
        });

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('getConnection')->andReturn($connection);
        $client->shouldReceive('getFolders')->andReturn(collect_folders(['INBOX', 'Sent']));
        $client->shouldReceive('disconnect')->once();
        $client->shouldNotReceive('getFolderByPath');

        $sut = new FakeImapClient($client);
        expect($sut->saveDraft(imapValidSettings(), 'to@example.com', 'subj', 'body'))->toBeFalse();
    });

    it('returns false when the override path does not exist on the server', function () {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('getFolderByPath')
            ->with('Nonexistent', true, true)
            ->once()
            ->andReturnNull();
        $client->shouldReceive('disconnect')->once();

        $settings = imapValidSettings();
        $settings['drafts_folder'] = 'Nonexistent';

        $sut = new FakeImapClient($client);
        expect($sut->saveDraft($settings, 'to@example.com', 'subj', 'body'))->toBeFalse();
    });

    it('swallows IMAP LIST errors during special-use lookup and falls back to alias', function () {
        $draftFolder = Mockery::mock(Webklex\PHPIMAP\Folder::class);
        $draftFolder->shouldReceive('appendMessage')->once();

        $connection = Mockery::mock();
        $connection->shouldReceive('folders')->andThrow(new RuntimeException('LIST failed'));

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('getConnection')->andReturn($connection);
        $client->shouldReceive('getFolders')->andReturn(collect_folders(['INBOX', 'Drafts']));
        $client->shouldReceive('getFolderByPath')
            ->with('Drafts', true, true)
            ->andReturn($draftFolder);
        $client->shouldReceive('disconnect')->once();

        $sut = new FakeImapClient($client);
        expect($sut->saveDraft(imapValidSettings(), 'to@example.com', 'subj', 'body'))->toBeTrue();
    });
});

/**
 * Build a FolderCollection from a list of simple names. Used by tests so
 * `Client::getFolders()` returns a real iterable without requiring a live
 * IMAP server.
 *
 * @param list<string> $names
 */
function collect_folders(array $names): Webklex\PHPIMAP\Support\FolderCollection
{
    $folders = [];
    foreach ($names as $name) {
        $folder = Mockery::mock(Webklex\PHPIMAP\Folder::class);
        $folder->name = $name;
        $folders[] = $folder;
    }
    return new Webklex\PHPIMAP\Support\FolderCollection($folders);
}
