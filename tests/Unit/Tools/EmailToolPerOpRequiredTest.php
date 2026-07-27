<?php

declare(strict_types=1);

use Spora\Plugins\Email\Tools\EmailTool;
use Spora\Tools\Attributes\ToolParameter;

/**
 * Per-op `required[]` binding tests for EmailTool.
 *
 * Reads `#[ToolParameter]` constructor arguments via reflection and asserts
 * each per-op required list. Independent of the bound spora-core version —
 * once spora-core ships the `bool|array $required` signature AND the
 * plugin bumps its dep, replace the reflection with a proper
 * `ToolParameterSchemaBuilder::build(EmailTool::class)` round-trip.
 */
function emailToolParameterArgs(string $name): array
{
    $reflection = new ReflectionClass(EmailTool::class);
    foreach ($reflection->getAttributes(ToolParameter::class) as $attribute) {
        $args = $attribute->getArguments();
        if (($args['name'] ?? null) === $name) {
            return $args;
        }
    }

    throw new RuntimeException("ToolParameter '{$name}' not declared on " . EmailTool::class);
}

it('binds limit, mark_as_read, unread_only to read_inbox only', function () {
    expect(emailToolParameterArgs('limit')['required'])->toBe(['read_inbox']);
    expect(emailToolParameterArgs('mark_as_read')['required'])->toBe(['read_inbox']);
    expect(emailToolParameterArgs('unread_only')['required'])->toBe(['read_inbox']);
});

it('binds folder to the 6 ops that read it', function () {
    expect(emailToolParameterArgs('folder')['required'])->toBe([
        'read_folder', 'rename_folder', 'delete_folder', 'move_email', 'delete_email', 'mark_email_read',
    ]);
});

it('binds to to send_email only', function () {
    expect(emailToolParameterArgs('to')['required'])->toBe(['send_email']);
});

it('binds subject and body to send_email + create_draft', function () {
    expect(emailToolParameterArgs('subject')['required'])->toBe(['send_email', 'create_draft']);
    expect(emailToolParameterArgs('body')['required'])->toBe(['send_email', 'create_draft']);
});

it('binds new_folder to create/rename/move ops', function () {
    expect(emailToolParameterArgs('new_folder')['required'])->toBe(['create_folder', 'rename_folder', 'move_email']);
});

it('binds uid to move/delete/mark_email_read', function () {
    expect(emailToolParameterArgs('uid')['required'])->toBe(['move_email', 'delete_email', 'mark_email_read']);
});

it('binds read to mark_email_read only', function () {
    expect(emailToolParameterArgs('read')['required'])->toBe(['mark_email_read']);
});
