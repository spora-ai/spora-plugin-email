<?php

declare(strict_types=1);

use Spora\Plugins\Email\Email\EmailActionDescriber;

/**
 * The catalogue, English source text to German.
 *
 * @return array<string, string>
 */
function emailCatalogue(): array
{
    /** @var array<string, string> $catalogue */
    $catalogue = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/locales/de.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    return $catalogue;
}

/**
 * The catalogue entry a rendered description resolves to, the way the host's
 * translator picks one: an exact key first, otherwise the most literal
 * `%s`/`%d` template that matches it. A tie between two different German
 * values is a miss rather than a guess, so it resolves to nothing.
 *
 * @param array<string, string> $catalogue
 *
 * @return array{key: string, value: string}|null
 */
function resolveCatalogueEntry(string $description, array $catalogue): ?array
{
    if (isset($catalogue[$description])) {
        return ['key' => $description, 'value' => $catalogue[$description]];
    }

    $bestKey = null;
    $bestValue = null;
    $bestScore = -1;
    $ambiguous = false;

    foreach ($catalogue as $key => $value) {
        $specifiers = substr_count($key, '%s') + substr_count($key, '%d');
        if (!str_contains($key, '%') || substr_count($key, '%') !== $specifiers) {
            continue;
        }

        $pattern = '/^' . str_replace(
            ['%s', '%d'],
            ['(.+?)', '(-?\d+)'],
            preg_quote($key, '/'),
        ) . '$/u';

        if (preg_match($pattern, $description) !== 1) {
            continue;
        }

        $score = strlen(str_replace(['%s', '%d'], '', $key));

        if ($score > $bestScore) {
            $bestKey = $key;
            $bestValue = $value;
            $bestScore = $score;
            $ambiguous = false;
        } elseif ($score === $bestScore && $bestValue !== $value) {
            $ambiguous = true;
        }
    }

    if ($bestKey === null || $ambiguous) {
        return null;
    }

    return ['key' => $bestKey, 'value' => $bestValue];
}

it('renders a description the German catalogue can translate', function (string $operation, array $arguments) {
    $description = (new EmailActionDescriber())->describe($operation, $arguments);

    $entry = resolveCatalogueEntry($description, emailCatalogue());

    expect($entry)->not->toBeNull("no catalogue entry for: {$description}");
})->with([
    'send_email' => ['send_email', ['to' => 'bob@example.com', 'subject' => 'Hello']],
    'list_folders' => ['list_folders', []],
    'read_folder' => ['read_folder', ['folder' => 'INBOX']],
    'create_draft' => ['create_draft', []],
    'read_inbox' => ['read_inbox', []],
    'read_inbox unread' => ['read_inbox', ['unread_only' => true]],
    'create_folder' => ['create_folder', ['new_folder' => 'MyFolder']],
    'rename_folder' => ['rename_folder', ['folder' => 'Old', 'new_folder' => 'New']],
    'delete_folder' => ['delete_folder', ['folder' => 'Trash']],
    'move_email' => ['move_email', ['uid' => 42, 'folder' => 'INBOX', 'new_folder' => 'Archive']],
    'delete_email' => ['delete_email', ['uid' => 42, 'folder' => 'INBOX']],
    'mark_email_read' => ['mark_email_read', ['uid' => 42, 'read' => true]],
    'mark_email_unread' => ['mark_email_read', ['uid' => 42, 'read' => false]],
]);

it('keeps the German placeholders in step with the English template', function (string $operation, array $arguments) {
    $description = (new EmailActionDescriber())->describe($operation, $arguments);

    $entry = resolveCatalogueEntry($description, emailCatalogue());

    expect($entry)->not->toBeNull("no catalogue entry for: {$description}");

    preg_match_all('/%[sd]/', $entry['key'], $english);
    preg_match_all('/%[sd]/', $entry['value'], $german);

    expect($german[0])->toBe($english[0]);
})->with([
    'send_email' => ['send_email', ['to' => 'bob@example.com', 'subject' => 'Hello']],
    'create_folder' => ['create_folder', ['new_folder' => 'MyFolder']],
    'rename_folder' => ['rename_folder', ['folder' => 'Old', 'new_folder' => 'New']],
    'delete_folder' => ['delete_folder', ['folder' => 'Trash']],
    'move_email' => ['move_email', ['uid' => 42, 'folder' => 'INBOX', 'new_folder' => 'Archive']],
    'delete_email' => ['delete_email', ['uid' => 42, 'folder' => 'INBOX']],
    'mark_email_read' => ['mark_email_read', ['uid' => 42, 'read' => true]],
    'mark_email_unread' => ['mark_email_read', ['uid' => 42, 'read' => false]],
]);
