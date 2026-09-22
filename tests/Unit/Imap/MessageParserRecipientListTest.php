<?php

declare(strict_types=1);

use Spora\Plugins\Email\Imap\MessageParser;

describe('MessageParser::parseRecipientList', function () {

    it('returns empty list for empty input', function () {
        expect(MessageParser::parseRecipientList(''))->toBe([]);
    });

    it('returns empty list for whitespace-only input', function () {
        expect(MessageParser::parseRecipientList('   '))->toBe([]);
    });

    it('returns single entry for a bare address', function () {
        expect(MessageParser::parseRecipientList('alice@example.com'))->toBe([
            ['name' => null, 'email' => 'alice@example.com'],
        ]);
    });

    it('splits RFC 5322 comma-separated addresses', function () {
        expect(MessageParser::parseRecipientList(
            'alice@example.com, bob@example.com',
        ))->toBe([
            ['name' => null, 'email' => 'alice@example.com'],
            ['name' => null, 'email' => 'bob@example.com'],
        ]);
    });

    it('normalizes semicolon separators to commas', function () {
        expect(MessageParser::parseRecipientList(
            'alice@example.com; bob@example.com',
        ))->toBe([
            ['name' => null, 'email' => 'alice@example.com'],
            ['name' => null, 'email' => 'bob@example.com'],
        ]);
    });

    it('mixes semicolon and comma separators', function () {
        expect(MessageParser::parseRecipientList(
            'alice@example.com; bob@example.com, eve@example.com',
        ))->toBe([
            ['name' => null, 'email' => 'alice@example.com'],
            ['name' => null, 'email' => 'bob@example.com'],
            ['name' => null, 'email' => 'eve@example.com'],
        ]);
    });

    it('preserves display names', function () {
        expect(MessageParser::parseRecipientList(
            'Alice <alice@example.com>, Bob <bob@example.com>',
        ))->toBe([
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ]);
    });

    it('preserves display names with semicolon separator', function () {
        expect(MessageParser::parseRecipientList(
            'Alice <alice@example.com>; Bob <bob@example.com>',
        ))->toBe([
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ]);
    });

    it('skips entries with no email (defensive)', function () {
        // "Bob" alone is a quoted display name without an angle address; the
        // parser still emits it but with an empty email. parseRecipientList
        // filters those out so the downstream Symfony Address builder doesn't
        // throw on an empty string.
        expect(MessageParser::parseRecipientList(
            '"Bob", alice@example.com',
        ))->toBe([
            ['name' => null, 'email' => 'alice@example.com'],
        ]);
    });
});
