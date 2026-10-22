<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CjPidClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CjReclaimClaims extends Command
{
    protected $signature = 'cj:reclaim-claims {--force : Force delete all claims} {--pattern= : Pattern to match (default cj:processing:*)} {--dry-run : Do not delete, only report}';

    protected $description = 'Reclaim stuck CJ PID claims in Redis';

    public function handle(): int
    {
        $pattern = $this->option('pattern') ?? 'cj:processing:*';
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');

        $this->line("Scanning Redis keys by pattern: $pattern (using SCAN)");

        $claimService = app(CjPidClaimService::class);
        $rows = [];

        $cursor = 0;
        do {
            $result = Redis::scan($cursor, 'MATCH', $pattern, 'COUNT', 1000);
            if (!is_array($result)) {
                break;
            }

            // Redis::scan may return [cursor, keys] for some clients
            if (array_key_exists(0, $result) && array_key_exists(1, $result) && is_array($result[1])) {
                $cursor = (int)$result[0];
                $keys = $result[1];
            } else {
                // Predis returns [$cursor, $keys]
                [$cursor, $keys] = $result;
            }

            foreach ($keys as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                // skip owner set keys
                if (strpos($key, 'owner:') !== false) {
                    continue;
                }

                // key is like cj:processing:{pid}
                $ttl = Redis::ttl($key);
                $value = Redis::get($key);
                $parts = explode('|', (string)$value, 2);
                $token = $parts[0] ?? null;
                $owner = $parts[1] ?? null;
                $pid = substr($key, strlen('cj:processing:'));

                $rows[] = [$pid, $ttl, $owner, $token];

                if ($dry) {
                    continue;
                }

                // Decide deletion: force => delete all; otherwise delete keys without TTL (ttl == -1)
                if ($force || $ttl === -1) {
                    $claimService->forceRelease($pid);
                    $this->line("Released claim for pid: $pid (owner: $owner)");
                }
            }

        } while ($cursor != 0);

        $this->table(['pid', 'ttl', 'owner', 'token'], $rows);

        if ($dry) {
            $this->info('Dry run complete — no keys deleted');
        }

        return Command::SUCCESS;
    }
}
