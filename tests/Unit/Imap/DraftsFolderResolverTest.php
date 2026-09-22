<?php

declare(strict_types=1);

use Spora\Plugins\Email\Imap\DraftsFolderResolver;

describe('DraftsFolderResolver::pickBySpecialUseFlag', function () {

    it('picks the first folder with the RFC 6154 \\Drafts flag', function () {
        $raw = [
            'INBOX' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren']],
            'Drafts' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren', '\\Drafts']],
            'Sent' => ['delimiter' => '/', 'flags' => ['\\Sent', '\\HasNoChildren']],
        ];
        expect(DraftsFolderResolver::pickBySpecialUseFlag($raw))->toBe('Drafts');
    });

    it('returns the Gmail-style [Gmail]/Drafts path when only that folder has the flag', function () {
        $raw = [
            'INBOX' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren']],
            '[Gmail]/Drafts' => ['delimiter' => '/', 'flags' => ['\\HasChildren', '\\Drafts']],
            '[Gmail]/Sent Mail' => ['delimiter' => '/', 'flags' => ['\\Sent', '\\HasNoChildren']],
        ];
        expect(DraftsFolderResolver::pickBySpecialUseFlag($raw))->toBe('[Gmail]/Drafts');
    });

    it('returns null when no folder has the \\Drafts flag', function () {
        $raw = [
            'INBOX' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren']],
            'Sent' => ['delimiter' => '/', 'flags' => ['\\Sent']],
        ];
        expect(DraftsFolderResolver::pickBySpecialUseFlag($raw))->toBeNull();
    });

    it('ignores malformed LIST entries', function () {
        $raw = [
            'INBOX' => 'not-an-array',
            'Broken' => ['delimiter' => '/'],
            'NoFlags' => ['delimiter' => '/', 'flags' => 'string-not-array'],
            'Drafts' => ['delimiter' => '/', 'flags' => ['\\Drafts']],
        ];
        expect(DraftsFolderResolver::pickBySpecialUseFlag($raw))->toBe('Drafts');
    });

    it('returns null on empty LIST', function () {
        expect(DraftsFolderResolver::pickBySpecialUseFlag([]))->toBeNull();
    });
});

describe('DraftsFolderResolver::pickByAlias', function () {

    it('matches Drafts (English name)', function () {
        expect(DraftsFolderResolver::pickByAlias(['INBOX', 'Sent', 'Drafts', 'Trash']))->toBe('Drafts');
    });

    it('matches Draft (Yahoo, singular)', function () {
        expect(DraftsFolderResolver::pickByAlias(['INBOX', 'Sent', 'Draft', 'Trash']))->toBe('Draft');
    });

    it('is case-insensitive (DRAFTS)', function () {
        expect(DraftsFolderResolver::pickByAlias(['INBOX', 'Sent', 'DRAFTS']))->toBe('DRAFTS');
    });

    it('matches INBOX/Drafts (Dovecot alternate namespace)', function () {
        expect(DraftsFolderResolver::pickByAlias(['INBOX', 'INBOX/Drafts']))->toBe('INBOX/Drafts');
    });

    it('returns null when nothing matches', function () {
        expect(DraftsFolderResolver::pickByAlias(['INBOX', 'Sent', 'Trash']))->toBeNull();
    });

    it('returns null on empty folder list', function () {
        expect(DraftsFolderResolver::pickByAlias([]))->toBeNull();
    });
});

describe('DraftsFolderResolver::resolve', function () {

    it('returns the override verbatim when set, ignoring LIST and aliases', function () {
        $resolver = new DraftsFolderResolver();
        $rawList = ['AlreadyFound' => ['flags' => ['\\Drafts']]];
        $names = ['Drafts'];

        expect($resolver->resolve('[Gmail]/Drafts', $rawList, $names))->toBe('[Gmail]/Drafts');
    });

    it('trims whitespace around the override', function () {
        $resolver = new DraftsFolderResolver();

        expect($resolver->resolve('  Drafts  ', [], []))->toBe('Drafts');
    });

    it('treats empty override as no override (falls through to flag/alias)', function () {
        $resolver = new DraftsFolderResolver();
        $rawList = ['Drafts' => ['flags' => ['\\Drafts']]];

        expect($resolver->resolve('', $rawList, []))->toBe('Drafts');
    });

    it('prefers \\Drafts flag over alias when no override', function () {
        $resolver = new DraftsFolderResolver();
        $rawList = ['Archive/Drafts' => ['flags' => ['\\Drafts']]];
        $names = ['Drafts'];

        expect($resolver->resolve('', $rawList, $names))->toBe('Archive/Drafts');
    });

    it('falls back to alias when no \\Drafts flag', function () {
        $resolver = new DraftsFolderResolver();
        $rawList = ['INBOX' => ['flags' => []]];

        expect($resolver->resolve('', $rawList, ['INBOX', 'Drafts', 'Sent']))->toBe('Drafts');
    });

    it('returns null when nothing resolves', function () {
        $resolver = new DraftsFolderResolver();
        $rawList = ['INBOX' => ['flags' => []]];

        expect($resolver->resolve('', $rawList, ['INBOX', 'Sent']))->toBeNull();
    });
});
