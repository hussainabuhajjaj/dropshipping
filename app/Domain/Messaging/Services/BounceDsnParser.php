<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Services;

final class BounceDsnParser
{
    /**
     * Extract the original recipient addresses from a delivery-status
     * notification (RFC 3464 DSN) or mailer failure notice.
     *
     * @return string[]
     */
    public static function extractRecipients(string $raw): array
    {
        $addresses = [];

        if (preg_match_all('/^Final-Recipient:\s*rfc822;\s*<?([^>\s,]+)>?/mi', $raw, $matches)) {
            foreach ($matches[1] as $address) {
                $addresses[] = trim($address, " \t\n\r\0\x0B<>");
            }
        }

        if (preg_match_all('/^X-Failed-Recipients:\s*(.+)$/mi', $raw, $matches)) {
            foreach (preg_split('/[\s,;]+/', $matches[1][0] ?? '') as $address) {
                $address = trim($address, " \t\n\r\0\x0B<>");
                if ($address !== '') {
                    $addresses[] = $address;
                }
            }
        }

        return array_values(array_unique(array_filter(
            $addresses,
            fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        )));
    }
}
