<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\ReviewHelpfulVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewHelpfulController extends Controller
{
    public function vote(Request $request, ProductReview $review): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $ipAddress = $request->ip();

        if (! $customer && ! $ipAddress) {
            return response()->json(['error' => 'Unable to record vote'], 400);
        }

        $existingVote = ReviewHelpfulVote::query()
            ->where('review_id', $review->id)
            ->when($customer, fn ($q) => $q->where('customer_id', $customer->id))
            ->when(! $customer, fn ($q) => $q->where('ip_address', $ipAddress))
            ->exists();

        if ($existingVote) {
            return response()->json(['error' => 'Already voted'], 409);
        }

        ReviewHelpfulVote::create([
            'review_id' => $review->id,
            'customer_id' => $customer?->id,
            'ip_address' => ! $customer ? $ipAddress : null,
        ]);

        $review->increment('helpful_count');
        $review->refresh();

        return response()->json([
            'success' => true,
            'helpful_count' => $review->helpful_count,
        ]);
    }
}
