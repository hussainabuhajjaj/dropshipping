<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\User\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly UserPreferenceService $preferenceService
    ) {}

    /**
     * Set user's preferred currency
     */
    public function setCurrency(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'in:' . implode(',', $this->preferenceService->getAvailableCurrencies())],
        ]);

        $this->preferenceService->setCurrency($validated['currency']);

        return redirect()->back()->with('success', 'Currency updated successfully');
    }

    /**
     * Set user's preferred language
     */
    public function setLanguage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', 'in:' . implode(',', array_keys($this->preferenceService->getAvailableLanguages()))],
        ]);

        $this->preferenceService->setLanguage($validated['language']);

        return redirect()->back()->with('success', 'Language updated successfully');
    }

    /**
     * Get current user preferences
     */
    public function getPreferences(): JsonResponse
    {
        return response()->json($this->preferenceService->getPreferences());
    }

    /**
     * Set both currency and language at once
     */
    public function setPreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'in:' . implode(',', $this->preferenceService->getAvailableCurrencies())],
            'language' => ['required', 'string', 'in:' . implode(',', array_keys($this->preferenceService->getAvailableLanguages()))],
        ]);

        $this->preferenceService->setCurrency($validated['currency']);
        $this->preferenceService->setLanguage($validated['language']);

        return back()->with('success', 'Preferences updated successfully');
    }
}
