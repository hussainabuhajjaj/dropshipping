<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Announcements\AnnouncementIndexRequest;
use App\Http\Resources\Mobile\V1\AnnouncementResource;
use App\Models\MobileAnnouncement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends ApiController
{
    public function index(AnnouncementIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 10), 50);
        $locale = app()->getLocale();

        $announcements = MobileAnnouncement::query()
            ->where('enabled', true)
            ->where(function ($query) use ($locale) {
                $query->whereNull('locale')
                    ->orWhere('locale', $locale);
            })
            ->latest()
            ->paginate($perPage);

        return $this->success(
            AnnouncementResource::collection($announcements->getCollection()),
            null,
            200,
            [
                'currentPage' => $announcements->currentPage(),
                'lastPage' => $announcements->lastPage(),
                'perPage' => $announcements->perPage(),
                'total' => $announcements->total(),
            ]
        );
    }
}

