<?php

declare(strict_types=1);

namespace App\Filament\Livewire;

use App\Services\Notifications\NotificationPresenter;
use Filament\Actions\Action;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification as DatabaseNotificationModel;
use Illuminate\Support\Str;

class AdminDatabaseNotifications extends DatabaseNotifications
{
    public function getNotificationsQuery(): Builder | Relation
    {
        $user = $this->getUser();

        if (! $user) {
            abort(401);
        }

        /** @phpstan-ignore-next-line */
        return $user->notifications()->latest();
    }

    public function getNotification(DatabaseNotificationModel $notification): Notification
    {
        $payload = is_array($notification->data) ? $notification->data : [];

        if (($payload['format'] ?? null) === 'filament') {
            return parent::getNotification($notification);
        }

        $formatted = app(NotificationPresenter::class)->format($notification);

        $title = trim((string) ($formatted['title'] ?? 'Notification'));
        $body = trim((string) ($formatted['body'] ?? ''));

        $filamentNotification = Notification::make((string) $notification->getKey())
            ->title($title !== '' ? $title : 'Notification')
            ->date($this->formatNotificationDate($notification->getAttributeValue('created_at')))
            ->icon($notification->read_at ? 'heroicon-o-bell' : 'heroicon-o-bell-alert')
            ->iconColor($notification->read_at ? 'gray' : 'warning');

        if ($body !== '') {
            $filamentNotification->body(Str::limit($body, 300));
        }

        $actionUrl = trim((string) ($formatted['action_url'] ?? ''));
        if ($actionUrl !== '') {
            $filamentNotification->actions([
                Action::make('open')
                    ->label((string) ($formatted['action_label'] ?? 'Open'))
                    ->url($actionUrl, shouldOpenInNewTab: true)
                    ->button(),
            ]);
        }

        return $filamentNotification;
    }
}
