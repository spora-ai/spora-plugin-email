<?php

declare(strict_types=1);

use Spora\Plugins\Email\Email\RecipientAddressBuilder;
use Symfony\Component\Mime\Address;

describe('RecipientAddressBuilder::parse', function () {

    it('returns empty list for empty input', function () {
        expect(RecipientAddressBuilder::parse(''))->toBe([]);
    });

    it('builds bare Address for a single address', function () {
        $addresses = RecipientAddressBuilder::parse('alice@example.com');

        expect($addresses)->toHaveCount(1);
        expect($addresses[0])->toBeInstanceOf(Address::class);
        expect($addresses[0]->getAddress())->toBe('alice@example.com');
        expect($addresses[0]->getName())->toBe('');
    });

    it('builds named Address for display-name input', function () {
        $addresses = RecipientAddressBuilder::parse('Alice <alice@example.com>');

        expect($addresses)->toHaveCount(1);
        expect($addresses[0]->getAddress())->toBe('alice@example.com');
        expect($addresses[0]->getName())->toBe('Alice');
    });

    it('builds multiple Addresses for a comma-separated input', function () {
        $addresses = RecipientAddressBuilder::parse('alice@example.com, bob@example.com');

        expect($addresses)->toHaveCount(2);
        expect($addresses[0]->getAddress())->toBe('alice@example.com');
        expect($addresses[1]->getAddress())->toBe('bob@example.com');
    });

    it('builds multiple Addresses for a semicolon-separated input', function () {
        $addresses = RecipientAddressBuilder::parse('alice@example.com; bob@example.com');

        expect($addresses)->toHaveCount(2);
        expect($addresses[0]->getAddress())->toBe('alice@example.com');
        expect($addresses[1]->getAddress())->toBe('bob@example.com');
    });

    it('preserves display names across multiple recipients', function () {
        $addresses = RecipientAddressBuilder::parse(
            'Alice <alice@example.com>, Bob <bob@example.com>',
        );

        expect($addresses)->toHaveCount(2);
        expect($addresses[0]->getName())->toBe('Alice');
        expect($addresses[1]->getName())->toBe('Bob');
    });

    it('mixes named and unnamed recipients', function () {
        $addresses = RecipientAddressBuilder::parse(
            'alice@example.com, Bob <bob@example.com>',
        );

        expect($addresses)->toHaveCount(2);
        expect($addresses[0]->getName())->toBe('');
        expect($addresses[1]->getName())->toBe('Bob');
    });
});
