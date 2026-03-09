<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Services\Analytics\VisitAnalyticsService;
use App\Services\Analytics\VisitTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitAnalyticsController extends ApiController
{
    public function __construct(
        private readonly VisitTrackingService $visitTrackingService,
        private readonly VisitAnalyticsService $visitAnalyticsService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_key' => ['required', 'string', 'max:100'],
            'screen' => ['required', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:32'],
            'entity_type' => ['nullable', 'string', 'max:40'],
            'entity_id' => ['nullable', 'integer'],
            'entity_slug' => ['nullable', 'string', 'max:191'],
            'metadata' => ['nullable', 'array'],
        ]);

        $this->visitTrackingService->trackMobileVisit($validated, $request);

        return $this->success([
            'tracked' => true,
        ]);
    }

    public function summary(): JsonResponse
    {
        return $this->success($this->visitAnalyticsService->summary());
    }
}
