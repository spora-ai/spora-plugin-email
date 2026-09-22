<?php

declare(strict_types=1);

namespace Spora\Plugins\Email\Email;

use Spora\Plugins\Email\Imap\MessageParser;
use Symfony\Component\Mime\Address;

// Splat into Symfony's Email::to() — a single comma-separated string
// fails RFC 5322 validation.
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
