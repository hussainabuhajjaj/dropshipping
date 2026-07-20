<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\User\UserPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserPreferenceController extends Controller
{
    public function __construct(
        private readonly UserPreferenceService $preferenceService
    ) {}

    /**
     * Get all user preferences
     */
    public function index(): JsonResponse
    {
        return response()->json($this->preferenceService->getPreferences());
    }

    /**
     * Update currency preference
     */
    public function updateCurrency(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'in:' . implode(',', $this->preferenceService->getAvailableCurrencies())]
        ]);

        try {
            $this->preferenceService->setCurrency($validated['currency']);

            if (request()->header('X-Inertia')) {
                return Inertia::location(url()->previous('/'));
            }

            return back(303)->with('success', 'Currency updated successfully');
        } catch (\InvalidArgumentException $e) {
            if (request()->header('X-Inertia')) {
                return back(303)->withErrors(['currency' => $e->getMessage()]);
            }

            return back(303)->withErrors(['currency' => $e->getMessage()]);
        }
    }

    /**
     * Update language preference
     */
    public function updateLanguage(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', 'in:' . implode(',', array_keys($this->preferenceService->getAvailableLanguages()))]
        ]);

        try {
            $this->preferenceService->setLanguage($validated['language']);

            if (request()->header('X-Inertia')) {
                return Inertia::location(url()->previous('/'));
            }

            return back(303)->with('success', 'Language updated successfully');
        } catch (\InvalidArgumentException $e) {
            if (request()->header('X-Inertia')) {
                return back(303)->withErrors(['language' => $e->getMessage()]);
            }

            return back(303)->withErrors(['language' => $e->getMessage()]);
        }
    }

    /**
     * Update multiple preferences at once
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['sometimes', 'string', 'in:' . implode(',', $this->preferenceService->getAvailableCurrencies())],
            'language' => ['sometimes', 'string', 'in:' . implode(',', array_keys($this->preferenceService->getAvailableLanguages()))]
        ]);

        try {
            if (isset($validated['currency'])) {
                $this->preferenceService->setCurrency($validated['currency']);
            }

            if (isset($validated['language'])) {
                $this->preferenceService->setLanguage($validated['language']);
            }

            if (request()->header('X-Inertia')) {
                return Inertia::location(url()->previous('/'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'preferences' => $this->preferenceService->getPreferences()
            ]);
        } catch (\InvalidArgumentException $e) {
            if (request()->header('X-Inertia')) {
                return back(303)->withErrors(['preferences' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
