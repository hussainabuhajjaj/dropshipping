<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\AdminLoginLog;
use Illuminate\Auth\Events\Login;

class LogAdminLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'admin') {
            return;
        }

        AdminLoginLog::create([
            'user_id' => $event->user->id,
            'logged_in_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
