<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Email;

use Spora\Plugins\Email\Imap\MessageParser;
use Symfony\Component\Mime\Address;

/**
 * Builds Symfony `Address` instances from a user-supplied `to` string.
 *
 * Symfony's `Email::to()` is variadic and treats a single comma-separated
 * string as one malformed address; we have to splat an actual array.
 */
final class RecipientAddressBuilder
{
    /**
     * @return list<Address>
     */
    public static function parse(string $to): array
    {
        $addresses = [];
        foreach (MessageParser::parseRecipientList($to) as $entry) {
            $addresses[] = $entry['name'] !== null
                ? new Address($entry['email'], $entry['name'])
                : new Address($entry['email']);
        }
        return $addresses;
    }
}
