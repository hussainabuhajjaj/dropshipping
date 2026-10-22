<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Fulfillment\Clients\CJ\CjErrorMapper;
use PHPUnit\Framework\TestCase;

class CjErrorMapperTest extends TestCase
{
    public function test_it_maps_interface_not_found(): void
    {
        $hint = CjErrorMapper::hint('1600101', 400, ['message' => 'Interface not found']);
        $this->assertStringContainsString('Interface not found', (string) $hint);
    }

    public function test_it_maps_rate_limit(): void
    {
        $hint = CjErrorMapper::hint('1600200', 429, ['message' => 'Rate limit']);
        $this->assertStringContainsString('Rate limit', (string) $hint);
    }
}
