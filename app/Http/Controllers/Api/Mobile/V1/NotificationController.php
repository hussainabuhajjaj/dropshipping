<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Notifications\NotificationIndexRequest;
use App\Http\Requests\Api\Mobile\V1\Notifications\NotificationMarkReadRequest;
use App\Http\Requests\Api\Mobile\V1\Notifications\ExpoTokenStoreRequest;
use App\Http\Requests\Api\Mobile\V1\Notifications\ExpoTokenDeleteRequest;
use App\Http\Resources\Mobile\V1\NotificationMarkReadResource;
use App\Http\Resources\Mobile\V1\NotificationResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use YieldStudio\LaravelExpoNotifier\Contracts\ExpoTokenStorageInterface;
use YieldStudio\LaravelExpoNotifier\Models\ExpoToken;

class NotificationController extends ApiController
{
    public function index(NotificationIndexRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 20), 50);

        $notifications = $customer->notifications()
            ->latest()
            ->paginate($perPage);

        return $this->success(
            NotificationResource::collection($notifications->getCollection()),
            null,
            200,
            [
                'currentPage' => $notifications->currentPage(),
                'lastPage' => $notifications->lastPage(),
                'perPage' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unreadCount' => $customer->unreadNotifications()->count(),
            ]
        );
    }

    public function markRead(NotificationMarkReadRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $validated = $request->validated();
        $ids = $validated['ids'] ?? [];
        if (! empty($validated['id'])) {
            $ids[] = $validated['id'];
        }

        $ids = array_values(array_unique(array_filter($ids)));

        $notifications = $customer->notifications()
            ->whereIn('id', $ids)
            ->get();

        $notifications->markAsRead();

        return $this->success(new NotificationMarkReadResource([
            'ok' => true,
            'read_ids' => $ids,
            'unread_count' => $customer->unreadNotifications()->count(),
        ]));
    }

    public function registerExpoToken(
        ExpoTokenStoreRequest $request,
        ExpoTokenStorageInterface $tokenStorage
    ): JsonResponse {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $token = (string) $request->validated()['token'];
        $alreadyRegistered = ExpoToken::query()
            ->where('owner_type', $customer->getMorphClass())
            ->where('owner_id', $customer->getKey())
            ->where('value', $token)
            ->exists();

        $tokenStorage->store($token, $customer);
        $tokenCount = (int) ExpoToken::query()
            ->where('owner_type', $customer->getMorphClass())
            ->where('owner_id', $customer->getKey())
            ->count();

        logger()->info('Mobile Expo token registered', [
            'customer_id' => $customer->getKey(),
            'already_registered' => $alreadyRegistered,
            'token_count' => $tokenCount,
            'token_preview' => Str::limit($token, 24, '...'),
        ]);

        return $this->success([
            'ok' => true,
            'token' => $token,
            'already_registered' => $alreadyRegistered,
            'token_count' => $tokenCount,
        ]);
    }

    public function removeExpoToken(ExpoTokenDeleteRequest $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $token = (string) $request->validated()['token'];

        $exists = ExpoToken::query()
            ->where('owner_type', $customer->getMorphClass())
            ->where('owner_id', $customer->getKey())
            ->where('value', $token)
            ->exists();

        if ($exists) {
            ExpoToken::query()
                ->where('owner_type', $customer->getMorphClass())
                ->where('owner_id', $customer->getKey())
                ->where('value', $token)
                ->delete();
        }

        $tokenCount = (int) ExpoToken::query()
            ->where('owner_type', $customer->getMorphClass())
            ->where('owner_id', $customer->getKey())
            ->count();

        logger()->info('Mobile Expo token removed', [
            'customer_id' => $customer->getKey(),
            'removed' => $exists,
            'token_count' => $tokenCount,
            'token_preview' => Str::limit($token, 24, '...'),
        ]);

        return $this->success([
            'ok' => true,
            'token' => $token,
            'removed' => $exists,
            'token_count' => $tokenCount,
        ]);
    }
}
