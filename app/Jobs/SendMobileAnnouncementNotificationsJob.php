<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use App\Models\MobileAnnouncement;
use App\Notifications\Marketing\MobileAnnouncementNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendMobileAnnouncementNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $announcementId)
    {
    }

    public function handle(): void
    {
        $announcement = MobileAnnouncement::query()->find($this->announcementId);

        if (! $announcement || ! $announcement->enabled) {
            return;
        }

        $query = Customer::query();
        if ($announcement->locale) {
            $query->where('locale', $announcement->locale);
        }

        $query->select(['id', 'email', 'metadata', 'locale'])
            ->chunkById(200, function ($customers) use ($announcement) {
                Notification::send($customers, new MobileAnnouncementNotification($announcement));
            });

        $announcement->forceFill(['notified_at' => now()])->save();
    }
}

