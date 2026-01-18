<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class NewsletterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        $email = strtolower(trim($data['email']));

        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            [
                'source' => $data['source'] ?? 'storefront_popup',
                'locale' => $request->getLocale(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'unsubscribed_at' => null,
            ]
        );

        $subscriber->ensureUnsubscribeToken();

        return response()->json([
            'status' => 'ok',
            'message' => 'Thanks for subscribing!',
            'subscriber_id' => $subscriber->id,
        ]);
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('unsubscribe_token', $token)
            ->first();

        if (! $subscriber) {
            return redirect()->route('home')->with('status', 'Subscription not found.');
        }

        if (! $subscriber->unsubscribed_at) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return redirect()->route('home')->with('status', 'You have been unsubscribed.');
    }
}
