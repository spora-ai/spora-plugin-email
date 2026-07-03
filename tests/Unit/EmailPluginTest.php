<?php

declare(strict_types=1);

use Spora\Plugins\Email\EmailPlugin;
use Spora\Plugins\Email\Tools\EmailTool;

it('returns plugin name', function () {
    $plugin = new EmailPlugin();
    expect($plugin->getName())->toBe('Email');
});

it('contributes the EmailTool', function () {
    $plugin = new EmailPlugin();
    expect($plugin->tools())->toBe([EmailTool::class]);
});
