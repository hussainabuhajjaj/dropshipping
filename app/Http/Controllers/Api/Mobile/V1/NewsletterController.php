<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\NewsletterSubscribeRequest;
use App\Http\Resources\Mobile\V1\NewsletterSubscriptionResource;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;

class NewsletterController extends ApiController
{
    public function subscribe(NewsletterSubscribeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $email = strtolower(trim($data['email']));

        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            [
                'source' => $data['source'] ?? 'mobile_popup',
                'locale' => $request->getLocale(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'unsubscribed_at' => null,
            ]
        );

        $subscriber->ensureUnsubscribeToken();

        return $this->success(new NewsletterSubscriptionResource([
            'subscriber_id' => $subscriber->id,
            'email' => $subscriber->email,
            'message' => 'Thanks for subscribing!',
        ]));
    }
}
