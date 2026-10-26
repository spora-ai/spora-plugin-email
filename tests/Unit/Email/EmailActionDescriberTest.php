<?php

declare(strict_types=1);

use Spora\Plugins\Email\Email\EmailActionDescriber;

const EMAIL_PLACEHOLDER_PATTERN = '/%[sd]/';

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

    preg_match_all(EMAIL_PLACEHOLDER_PATTERN, $entry['key'], $english);
    preg_match_all(EMAIL_PLACEHOLDER_PATTERN, $entry['value'], $german);

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

/**
 * The host matches the rendered sentence against the catalogue's `%s`
 * templates with a `(.+?)` pattern and no DOTALL modifier, so an argument
 * holding a newline never matched its template: `trans()` fell back to
 * English and a German operator approved an English prompt. The `s`
 * modifier lands in core, but the released core ships no translation
 * layer, so this plugin cannot exercise the host's matcher and asserts
 * only its own half — the prompt renders from a template the catalogue
 * carries, the German template carries the same placeholders, and the
 * multi-line argument reaches the slot unchanged.
 *
 * Once core ships the DOTALL fix, assert the German sentence too: the
 * host's `Translator::trans()` fills the German template.
 */
it('carries a multi-line subject into the approval prompt template the catalogue carries', function () {
    $catalogue = emailCatalogue();
    $template = "Sending email to %s with subject: '%s'";
    $german = $catalogue[$template] ?? '';
    $subject = "Quarterly report\nQ3 numbers attached";

    $description = (new EmailActionDescriber())->describe('send_email', [
        'to' => 'bob@example.com',
        'subject' => $subject,
    ]);

    expect($catalogue)->toHaveKey($template)
        ->and($german)->not->toBe('')
        ->and($description)->toBe(sprintf($template, 'bob@example.com', $subject));

    preg_match_all(EMAIL_PLACEHOLDER_PATTERN, $template, $english);
    preg_match_all(EMAIL_PLACEHOLDER_PATTERN, $german, $germanSpecifiers);

    expect($germanSpecifiers[0])->toBe($english[0]);
});
