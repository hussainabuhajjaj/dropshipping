<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Messaging\Services\BounceDsnParser;
use Tests\TestCase;

class BounceDsnParserTest extends TestCase
{
    public function test_extracts_final_recipient_rfc3464(): void
    {
        $raw = <<<'DSN'
This is the mail system at host mail.example.com.

Final-Recipient: rfc822; somebody@example.org
Original-Recipient: rfc822;somebody@example.org
Action: failed
Status: 5.1.1
DSN;

        $this->assertSame(['somebody@example.org'], BounceDsnParser::extractRecipients($raw));
    }

    public function test_extracts_final_recipient_with_angle_brackets(): void
    {
        $raw = <<<'DSN'
Final-Recipient: rfc822; <another@example.net>
Action: failed
DSN;

        $this->assertSame(['another@example.net'], BounceDsnParser::extractRecipients($raw));
    }

    public function test_extracts_x_failed_recipients_header(): void
    {
        $raw = "X-Failed-Recipients: a@example.com, b@example.com\n\nBody text here.\n";

        $this->assertSame(['a@example.com', 'b@example.com'], BounceDsnParser::extractRecipients($raw));
    }

    public function test_deduplicates_and_ignores_invalid_addresses(): void
    {
        $raw = <<<'DSN'
Final-Recipient: rfc822; dup@example.com
X-Failed-Recipients: dup@example.com not-an-email
DSN;

        $this->assertSame(['dup@example.com'], BounceDsnParser::extractRecipients($raw));
    }

    public function test_returns_empty_for_non_dsn_message(): void
    {
        $raw = "Just a normal email body without any recipient hints.\n";

        $this->assertSame([], BounceDsnParser::extractRecipients($raw));
    }
}
