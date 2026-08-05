<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Messaging\Services\BounceDsnParser;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportCustomerEmailBounces extends Command
{
    protected $signature = 'customers:import-bounces
        {--dry-run : Report matches without updating the database}
        {--unseen-only : Only scan unread messages}
        {--mark-seen : Mark scanned messages as read after processing}';

    protected $description = 'Scan the sender mailbox for delivery-failure (bounce) notifications and mark matching customers as email_bounced_at';

    public function handle(): int
    {
        if (! extension_loaded('imap')) {
            $this->error('The PHP imap extension is not available.');

            return self::FAILURE;
        }

        $host = (string) config('mail.bounce_imap.host');
        $port = (int) config('mail.bounce_imap.port');
        $user = (string) config('mail.bounce_imap.username');
        $pass = (string) config('mail.bounce_imap.password');

        if ($host === '' || $user === '' || $pass === '') {
            $this->error('IMAP credentials are not configured (IMAP_HOST/IMAP_USERNAME/IMAP_PASSWORD or MAIL_*).');

            return self::FAILURE;
        }

        $mailbox = "{{$host}:{$port}/imap/ssl/norsh}INBOX";
        $connection = @imap_open($mailbox, $user, $pass, OP_HALFOPEN, 3);

        if ($connection === false) {
            $this->error('Unable to connect: '.imap_last_error());

            return self::FAILURE;
        }

        try {
            $criteria = $this->option('unseen-only') ? 'UNSEEN' : 'ALL';
            $messageNumbers = imap_search($connection, $criteria) ?: [];

            $this->info("Scanning {$criteria} messages on {$host}...");

            $addresses = [];
            $dsnCount = 0;

            foreach ($messageNumbers as $number) {
                $number = (string) $number;
                $overview = imap_fetch_overview($connection, $number, 0);
                $subject = (string) ($overview[0]->subject ?? '');
                $from = (string) ($overview[0]->from ?? '');
                $sender = (string) ($overview[0]->fromaddress ?? '');

                if (! $this->looksLikeBounce($subject, $from, $sender)) {
                    continue;
                }

                $body = (string) imap_fetchbody($connection, $number, '');
                $found = BounceDsnParser::extractRecipients($body);

                if ($found === []) {
                    continue;
                }

                $dsnCount++;
                $addresses = array_merge($addresses, $found);

                if ($this->option('mark-seen')) {
                    imap_setflag_full($connection, $number, '\\Seen');
                }
            }

            $addresses = array_values(array_unique($addresses));

            $this->info("Found {$dsnCount} bounce message(s) with ".count($addresses).' recipient address(es).');

            if ($addresses === []) {
                return self::SUCCESS;
            }

            $matched = 0;
            $updated = 0;

            foreach ($addresses as $address) {
                $customer = Customer::query()
                    ->whereRaw('LOWER(email) = ?', [strtolower($address)])
                    ->first();

                if (! $customer) {
                    continue;
                }

                $matched++;

                if (! $this->option('dry-run') && $customer->email_bounced_at === null) {
                    $customer->markEmailBounced();
                    $updated++;
                }
            }

            $this->info("Matched {$matched} customer(s), marked {$updated} as bounced.");

            foreach ($addresses as $address) {
                $this->line("  - {$address}");
            }

            Log::info('Customer email bounce import', [
                'dsn_messages' => $dsnCount,
                'addresses' => $addresses,
                'matched' => $matched,
                'updated' => $updated,
            ]);

            return self::SUCCESS;
        } finally {
            imap_close($connection);
        }
    }

    private function looksLikeBounce(string $subject, string $from, string $sender): bool
    {
        $needle = $subject.' '.$from.' '.$sender;

        return (bool) preg_match(
            '/deliver|fail|undeliver|returned|bounce|daemon|postmaster|livraison|echec|retour|unzustellbar|nie doręczono/i',
            $needle
        );
    }
}
