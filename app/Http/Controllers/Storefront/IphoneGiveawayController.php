<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Campaigns\Services\LuckyDrawService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class IphoneGiveawayController extends Controller
{
    /**
     * Legacy route: forward to the active lucky-draw campaign page.
     * Kept as a redirect so pre-existing links / announcement bars keep working.
     */
    public function index(LuckyDrawService $service): RedirectResponse
    {
        $campaign = $service->activeLuckyDraw(app()->getLocale());

        if (! $campaign) {
            abort(404, 'No active campaign.');
        }

        return Redirect::route('campaigns.show', $campaign->slug);
    }
}
