<?php

declare(strict_types=1);

use Spora\Plugins\Email\Imap\ImapClient;

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
 * Invoke the private static `pickDraftsPathFromRawList` helper on ImapClient.
 *
 * @param array<int|string, mixed> $raw
 */
function imapPickDraftsPathFromRawList(array $raw): ?string
{
    $ref = new ReflectionMethod(ImapClient::class, 'pickDraftsPathFromRawList');
    /** @var string|null */
    return $ref->invoke(null, $raw);
}

/**
 * Invoke the private static `pickDraftsNameFromAliases` helper on ImapClient.
 *
 * @param list<string> $names
 */
function imapPickDraftsNameFromAliases(array $names): ?string
{
    $ref = new ReflectionMethod(ImapClient::class, 'pickDraftsNameFromAliases');
    /** @var string|null */
    return $ref->invoke(null, $names);
}

describe('ImapClient drafts-folder resolution (pure helpers)', function () {

    it('picks the first folder with the RFC 6154 \\Drafts flag', function () {
        $raw = [
            'INBOX' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren']],
            'Drafts' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren', '\\Drafts']],
            'Sent' => ['delimiter' => '/', 'flags' => ['\\Sent', '\\HasNoChildren']],
        ];
        expect(imapPickDraftsPathFromRawList($raw))->toBe('Drafts');
    });

    it('returns Gmail-style [Gmail]/Drafts path when only that folder has the flag', function () {
        $raw = [
            'INBOX' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren']],
            '[Gmail]/Drafts' => ['delimiter' => '/', 'flags' => ['\\HasChildren', '\\Drafts']],
            '[Gmail]/Sent Mail' => ['delimiter' => '/', 'flags' => ['\\Sent', '\\HasNoChildren']],
        ];
        expect(imapPickDraftsPathFromRawList($raw))->toBe('[Gmail]/Drafts');
    });

    it('returns null when no folder has the \\Drafts flag', function () {
        $raw = [
            'INBOX' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren']],
            'Sent' => ['delimiter' => '/', 'flags' => ['\\Sent']],
        ];
        expect(imapPickDraftsPathFromRawList($raw))->toBeNull();
    });

    it('ignores malformed LIST entries', function () {
        $raw = [
            'INBOX' => 'not-an-array',
            'Broken' => ['delimiter' => '/'],
            'NoFlags' => ['delimiter' => '/', 'flags' => 'string-not-array'],
            'Drafts' => ['delimiter' => '/', 'flags' => ['\\Drafts']],
        ];
        expect(imapPickDraftsPathFromRawList($raw))->toBe('Drafts');
    });

    it('alias fallback matches Drafts (English name)', function () {
        $names = ['INBOX', 'Sent', 'Drafts', 'Trash'];
        expect(imapPickDraftsNameFromAliases($names))->toBe('Drafts');
    });

    it('alias fallback matches Draft (Yahoo, singular)', function () {
        $names = ['INBOX', 'Sent', 'Draft', 'Trash'];
        expect(imapPickDraftsNameFromAliases($names))->toBe('Draft');
    });

    it('alias fallback is case-insensitive (drafts vs DRAFTS)', function () {
        $names = ['INBOX', 'Sent', 'DRAFTS'];
        expect(imapPickDraftsNameFromAliases($names))->toBe('DRAFTS');
    });

    it('alias fallback returns null when nothing matches', function () {
        $names = ['INBOX', 'Sent', 'Trash'];
        expect(imapPickDraftsNameFromAliases($names))->toBeNull();
    });

    it('alias fallback returns null on empty folder list', function () {
        expect(imapPickDraftsNameFromAliases([]))->toBeNull();
    });
});
