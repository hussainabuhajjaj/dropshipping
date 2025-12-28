<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\SyncCjProductsJob;
use App\Services\Api\ApiException;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase as BaseTestCase;

class TestableSyncJob extends SyncCjProductsJob
{
    public ?int $releasedDelay = null;

    public function release($delay = 0)
    {
        $this->releasedDelay = $delay;
    }
}

class SyncCjProductsJobTest extends BaseTestCase
{
    public function test_job_releases_on_429_with_backoff(): void
    {
        Config::set('services.cj', [
            'api_key' => 'k',
            'base_url' => 'https://example.test',
        ]);

        $client = $this->createMock(CJDropshippingClient::class);
        $client->expects($this->once())
            ->method('listProductsV2')
            ->willThrowException(new ApiException('Rate limit', 429, '1600200', ['message' => 'Rate limit'], 'REQ-1'));

        $job = new TestableSyncJob(1, 1);

        $job->handle($client);

        $this->assertNotNull($job->releasedDelay);
        $this->assertGreaterThan(0, $job->releasedDelay);
    }
}
