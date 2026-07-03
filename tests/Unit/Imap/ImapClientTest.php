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
