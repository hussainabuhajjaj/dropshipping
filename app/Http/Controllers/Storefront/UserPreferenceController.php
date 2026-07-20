<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\User\UserPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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

            // For Inertia requests, we need to redirect back with updated props
            if (request()->header('X-Inertia')) {
                return redirect()->back();
            }

            // For regular API requests, return JSON
            return response()->json([
                'success' => true,
                'message' => 'Currency updated successfully',
                'preferences' => $this->preferenceService->getPreferences()
            ]);
        } catch (\InvalidArgumentException $e) {
            if (request()->header('X-Inertia')) {
                return redirect()->back()->withErrors(['currency' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
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

            // For Inertia requests, we need to redirect back with updated props
            if (request()->header('X-Inertia')) {
                return redirect()->back();
            }

            // For regular API requests, return JSON
            return response()->json([
                'success' => true,
                'message' => 'Language updated successfully',
                'preferences' => $this->preferenceService->getPreferences()
            ]);
        } catch (\InvalidArgumentException $e) {
            if (request()->header('X-Inertia')) {
                return redirect()->back()->withErrors(['language' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
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

            // For Inertia requests, redirect back with updated props
            if (request()->header('X-Inertia')) {
                return redirect()->back();
            }

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'preferences' => $this->preferenceService->getPreferences()
            ]);
        } catch (\InvalidArgumentException $e) {
            if (request()->header('X-Inertia')) {
                return redirect()->back()->withErrors(['preferences' => $e->getMessage()]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
