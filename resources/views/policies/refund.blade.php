@extends('layouts.app')

@section('content')
@php
    /** @var \App\Models\SiteSetting|null $site */
    $site = \App\Models\SiteSetting::query()->first();
    $supportEmail = $site?->support_email ?? 'support@dispatch.store';
    $policyHtml = trim((string) ($site?->refund_policy ?? ''));
@endphp
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-4xl font-bold text-gray-900 mb-8">Refund & Return Policy</h1>

    <div class="prose prose-lg max-w-none space-y-6 text-gray-700">
        @if($policyHtml !== '')
            <div class="space-y-4 text-gray-700 leading-7" {!! 'v-pre' !!}>
                {!! $policyHtml !!}
            </div>
        @else
            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Returns & Exchanges</h2>
                <p class="text-base">
                    We offer a straightforward refund policy to protect your purchase.
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li><strong>30-day window:</strong> Request returns/refunds within 30 days of delivery.</li>
                    <li><strong>Original condition:</strong> Items must be unused and in original packaging.</li>
                    <li><strong>Return shipping:</strong> Customer covers return shipping costs unless item is defective.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Refund Scenarios</h2>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mt-4 mb-6">
                    <h3 class="font-bold text-blue-900 mb-2">A. Supplier or Shipping Issues (FULL REFUND)</h3>
                    <p class="text-base text-blue-800 mb-2">
                        If the supplier or carrier is responsible:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-blue-800">
                        <li>Item is out of stock after purchase</li>
                        <li>Package is lost during shipping</li>
                        <li>Delivery takes more than 30 days (without notice)</li>
                        <li>Item arrives defective or damaged by supplier</li>
                    </ul>
                    <p class="text-sm text-blue-800 mt-3 font-semibold">
                        ➜ <strong>100% refund</strong> automatically approved
                    </p>
                </div>

                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-4 mb-6">
                    <h3 class="font-bold text-yellow-900 mb-2">B. Shipping Address Issues (PARTIAL REFUND)</h3>
                    <p class="text-base text-yellow-800 mb-2">
                        If the issue is with the address or delivery attempt:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-yellow-800">
                        <li>Customer provided wrong address</li>
                        <li>Recipient refused delivery</li>
                        <li>Package returned to sender due to address issue</li>
                    </ul>
                    <p class="text-sm text-yellow-800 mt-3 font-semibold">
                        ➜ <strong>85% refund</strong> (deducting shipping cost)
                    </p>
                </div>

                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mt-4 mb-6">
                    <h3 class="font-bold text-purple-900 mb-2">C. Product Quality Issues (CASE-BY-CASE)</h3>
                    <p class="text-base text-purple-800 mb-2">
                        If you received the wrong item or quality is poor:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-purple-800">
                        <li>Item doesn't match description</li>
                        <li>Quality significantly below expectation</li>
                        <li>Item arrived damaged due to packaging</li>
                    </ul>
                    <p class="text-sm text-purple-800 mt-3 font-semibold">
                        ➜ <strong>50-100% refund</strong> based on assessment. Photos required.
                    </p>
                </div>

                <div class="bg-gray-50 border-l-4 border-gray-500 p-4 mt-4 mb-6">
                    <h3 class="font-bold text-gray-900 mb-2">D. Customer-Initiated Returns (LIMITED)</h3>
                    <p class="text-base text-gray-800 mb-2">
                        If you changed your mind or item isn't what you expected (but works fine):
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-gray-800">
                        <li>You can request a return within 30 days.</li>
                        <li>You cover return shipping.</li>
                        <li>Refund subject to 10-15% restocking fee.</li>
                    </ul>
                    <p class="text-sm text-gray-800 mt-3 font-semibold">
                        ➜ <strong>85% refund</strong> minus return shipping
                    </p>
                </div>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">How to Request a Refund</h2>
                <ol class="list-decimal list-inside space-y-3 mt-4">
                    <li>Log into your account and go to <strong>Order History</strong>.</li>
                    <li>Click the order you wish to refund.</li>
                    <li>Select <strong>Request Return</strong> and choose a reason.</li>
                    <li>Provide photos (if quality/damage issue).</li>
                    <li>We review and respond within 3-5 business days.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Refund Processing</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li><strong>Decision time:</strong> 3-5 business days after request.</li>
                    <li><strong>Processing time:</strong> 5-10 business days after approval.</li>
                    <li><strong>Your refund method:</strong> Original payment method (credit card, mobile wallet, etc).</li>
                    <li><strong>Tracking:</strong> You'll receive a refund reference number via email.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Non-Refundable Items</h2>
                <p class="text-base">
                    The following are generally non-refundable:
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>Digital products or downloadable content</li>
                    <li>Custom or personalized items (unless defective)</li>
                    <li>Items purchased during clearance/final sale</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Questions?</h2>
                <p class="text-base">
                    Contact us at <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:underline">{{ $supportEmail }}</a> with your order number. We're here to help!
                </p>
            </section>
        @endif
    </div>
</div>
@endsection
