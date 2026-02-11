<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CjPidClaimService
{
    private string $prefix = 'cj:processing:';

    /**
     * Attempt to claim a pid. Returns a token string on success, or null on failure.
     */
    public function claim(string $pid, int $ttlSeconds = 3600, ?string $owner = null): ?string
    {
        $key = $this->prefix . $pid;
        $token = bin2hex(random_bytes(16));
        $owner = $owner ?? (gethostname() . ':' . getmypid());

        // Store value as token|owner so we can identify which worker owns the claim
        $value = $token . '|' . $owner;

        // Use SET NX EX for atomic claim
        $ok = Redis::set($key, $value, 'EX', $ttlSeconds, 'NX');

        // Some Redis clients return a Status object, some boolean, and some
        // drivers may return null even when SET succeeded. Verify by reading
        // the key back to ensure the expected value was written.
        $current = Redis::get($key);
        if ($current === $value) {
            // Track this pid in an owner-specific set for quick cleanup on shutdown
            $ownerSetKey = $this->ownerSetKey($owner);
            Redis::sadd($ownerSetKey, $pid);
            // Set the same TTL on the owner set member via key expiry on the set (best-effort)
            Redis::expire($ownerSetKey, $ttlSeconds);
            return $token;
        }

        return null;
    }

    /**
     * Release a claim only if the token matches (atomic compare-and-del).
     * Returns true if the key was deleted.
     */
    public function release(string $pid, string $token): bool
    {
        $key = $this->prefix . $pid;

        // Fetch current value and compare token portion to allow token|owner storage
        $current = Redis::get($key);
        if ($current === null) {
            return false;
        }

        $parts = explode('|', (string) $current, 2);
        $currentToken = $parts[0] ?? '';
        $owner = $parts[1] ?? null;

        if (!hash_equals((string)$currentToken, (string)$token)) {
            return false;
        }

        // Delete the key and remove pid from owner's set
        $deleted = Redis::del($key);
        if ($owner) {
            Redis::srem($this->ownerSetKey($owner), $pid);
        }

        return (int) $deleted === 1;
    }

    /**
     * Force release (delete) claim key regardless of token. Use sparingly.
     */
    public function forceRelease(string $pid): void
    {
        $key = $this->prefix . $pid;
        $current = Redis::get($key);
        if ($current !== null) {
            $parts = explode('|', (string)$current, 2);
            $owner = $parts[1] ?? null;
            Redis::del($key);
            if ($owner) {
                Redis::srem($this->ownerSetKey($owner), $pid);
            }
        }
    }

    private function ownerSetKey(string $owner): string
    {
        return $this->prefix . 'owner:' . $owner;
    }

    /**
     * Release all claims associated with an owner id.
     */
    public function releaseAllForOwner(string $owner): int
    {
        $ownerSet = $this->ownerSetKey($owner);
        $pids = Redis::smembers($ownerSet) ?? [];
        $released = 0;
        foreach ($pids as $pid) {
            $this->forceRelease($pid);
            $released++;
        }
        // Remove the owner set key
        Redis::del($ownerSet);
        return $released;
    }
}
