<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use Notifiable;

    protected $fillable = [
        'email',
        'unsubscribe_token',
        'unsubscribed_at',
        'source',
        'locale',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'unsubscribed_at' => 'datetime',
    ];

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function ensureUnsubscribeToken(): void
    {
        if ($this->unsubscribe_token) {
            return;
        }

        $attempts = 0;
        while ($attempts < 3) {
            $attempts++;
            $this->unsubscribe_token = Str::random(48);

            try {
                $this->save();
                return;
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'newsletter_subscribers_unsubscribe_token_unique')) {
                    continue;
                }

                throw $e;
            }
        }
    }
}
