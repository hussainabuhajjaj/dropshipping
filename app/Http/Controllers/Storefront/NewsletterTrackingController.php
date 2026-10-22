<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaignLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NewsletterTrackingController extends Controller
{
    public function open(Request $request, string $token): Response
    {
        $log = NewsletterCampaignLog::query()->where('tracking_token', $token)->first();
        if ($log) {
            $log->update([
                'opened_at' => $log->opened_at ?? now(),
                'meta' => array_merge($log->meta ?? [], [
                    'last_open_ip' => $request->ip(),
                    'last_open_user_agent' => (string) $request->userAgent(),
                ]),
            ]);
        }

        // 1x1 transparent gif
        $gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function click(Request $request, string $token): RedirectResponse
    {
        $log = NewsletterCampaignLog::query()->with('campaign')->where('tracking_token', $token)->first();
        $target = $log?->campaign?->action_url ?: route('home');

        if ($log) {
            $log->update([
                'clicked_at' => $log->clicked_at ?? now(),
                'click_count' => ($log->click_count ?? 0) + 1,
                'meta' => array_merge($log->meta ?? [], [
                    'last_click_ip' => $request->ip(),
                    'last_click_user_agent' => (string) $request->userAgent(),
                ]),
            ]);
        }

        return redirect()->away($target);
    }
}
