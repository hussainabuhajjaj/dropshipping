<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Affiliates\Models\AffiliateWithdrawal;
use App\Domain\Affiliates\Services\AffiliateCouponService;
use App\Domain\Affiliates\Services\AffiliateReferralDiscountService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function __construct(
        private readonly AffiliateCouponService $couponService,
        private readonly AffiliateReferralDiscountService $referralDiscountService,
    ) {
    }

    public function signupForm(): Response
    {
        return Inertia::render('Affiliate/Signup', [
            'minWithdrawal' => config('affiliate.minimum_withdrawal', 50),
            'defaultCommissionRate' => config('affiliate.default_commission_rate', 0.10) * 100,
        ]);
    }

    public function signup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('affiliates', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agree_terms' => ['required', 'accepted'],
        ]);

        $affiliate = Affiliate::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'pending',
            'commission_rate' => config('affiliate.default_commission_rate', 0.10),
            'balance_pending' => 0,
            'balance_available' => 0,
            'total_earned' => 0,
        ]);

        $this->couponService->createStandardAffiliateCoupon($affiliate);

        Auth::guard('affiliate')->login($affiliate);

        return redirect()->to('/affiliate');
    }

    public function dashboard(): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $stats = [
            'total_commissions' => (clone $affiliate->commissions())->sum('commission_amount'),
            'pending_commissions' => (clone $affiliate->commissions())->where('status', 'pending')->count(),
            'approved_commissions' => (clone $affiliate->commissions())->where('status', 'approved')->count(),
            'total_withdrawn' => (clone $affiliate->withdrawals())->where('status', 'processed')->sum('amount'),
            'referral_count' => $affiliate->referrals()->count(),
            'converted_referrals' => $affiliate->referrals()->whereNotNull('user_id')->count(),
        ];

        $recentCommissions = $affiliate->commissions()
            ->with(['order', 'orderItem'])
            ->latest()
            ->limit(10)
            ->get();

        $chartMonths = collect(range(5, 0))->map(function ($i) use ($affiliate) {
            $date = now()->subMonths($i);
            $monthlyCommissions = (clone $affiliate->commissions())
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->whereIn('status', ['approved', 'paid'])
                ->sum('commission_amount');

            return [
                'month' => $date->format('M Y'),
                'earnings' => round((float) $monthlyCommissions, 2),
            ];
        });

        $coupon = $this->couponService->findOrCreateReferralCoupon($affiliate);

        return Inertia::render('Affiliate/Dashboard', [
            'affiliate' => $affiliate->only([
                'name', 'email', 'referral_code', 'commission_rate',
                'balance_pending', 'balance_available', 'total_earned', 'status',
            ]),
            'referral_link' => url('/?ref=' . $affiliate->referral_code),
            'referral_coupon' => $coupon->only(['code', 'type', 'amount', 'description']),
            'stats' => $stats,
            'chart_data' => $chartMonths,
            'recent_commissions' => $recentCommissions->map(fn ($c) => [
                'id' => $c->id,
                'order_number' => $c->order?->number,
                'commission_amount' => $c->commission_amount,
                'commission_rate' => $c->commission_rate,
                'status' => $c->status,
                'created_at' => $c->created_at->format('Y-m-d'),
            ]),
            'min_withdrawal' => config('affiliate.minimum_withdrawal', 50),
        ]);
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:' . config('affiliate.minimum_withdrawal', 50),
                'max:' . ($affiliate->balance_available ?? 0),
            ],
        ]);

        AffiliateWithdrawal::create([
            'affiliate_id' => $affiliate->id,
            'amount' => $validated['amount'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Withdrawal request submitted successfully.');
    }
}
