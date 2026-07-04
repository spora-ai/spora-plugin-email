<?php

declare(strict_types=1);

use DI\Container;
use DI\ContainerBuilder;
use Spora\Plugins\Email\EmailPlugin;
use Spora\Plugins\Email\Imap\ImapClient;
use Spora\Plugins\Email\Imap\ImapClientInterface;
use Spora\Plugins\Email\Tools\EmailTool;

it('returns plugin name', function () {
    $plugin = new EmailPlugin();
    expect($plugin->getName())->toBe('Email');
});

it('contributes the EmailTool', function () {
    $plugin = new EmailPlugin();
    expect($plugin->tools())->toBe([EmailTool::class]);
});

it('register() binds ImapClientInterface to ImapClient', function () {
    $plugin  = new EmailPlugin();
    $builder = new ContainerBuilder();
    $builder->useAutowiring(true);

    $plugin->register($builder);

    /** @var Container $container */
    $container = $builder->build();

    $imap = $container->get(ImapClientInterface::class);

    expect($imap)->toBeInstanceOf(ImapClient::class);
});
