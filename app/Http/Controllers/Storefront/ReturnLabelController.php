<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\ReturnLabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ReturnLabelController extends Controller
{
    /**
     * Download return label PDF for customer.
     */
    public function download(Request $request, int $returnRequestId): Response|RedirectResponse
    {
        $customer = $request->user('customer');
        
        if (! $customer) {
            abort(403, 'Unauthorized');
        }

        $returnRequest = ReturnRequest::query()
            ->where('id', $returnRequestId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        if (empty($returnRequest->return_label_url)) {
            return back()->withErrors(['label' => 'Return label not available yet']);
        }

        if ($returnRequest->status !== 'approved') {
            return back()->withErrors(['label' => 'Return must be approved before downloading label']);
        }

        try {
            $labelService = app(ReturnLabelService::class);
            $pdfContent = $labelService->downloadLabel($returnRequest->return_label_url);

            if (! $pdfContent) {
                return back()->withErrors(['label' => 'Failed to download label. Please try again.']);
            }

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="return-label-' . $returnRequest->id . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            Log::error('Return label download failed', [
                'return_request_id' => $returnRequestId,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['label' => 'Failed to download label. Please contact support.']);
        }
    }

    /**
     * Preview return label in browser.
     */
    public function preview(Request $request, int $returnRequestId): Response|RedirectResponse
    {
        $customer = $request->user('customer');
        
        if (! $customer) {
            abort(403, 'Unauthorized');
        }

        $returnRequest = ReturnRequest::query()
            ->where('id', $returnRequestId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        if (empty($returnRequest->return_label_url)) {
            return back()->withErrors(['label' => 'Return label not available yet']);
        }

        if ($returnRequest->status !== 'approved') {
            return back()->withErrors(['label' => 'Return must be approved before viewing label']);
        }

        // Redirect to the label URL for preview
        return redirect()->away($returnRequest->return_label_url);
    }
}
