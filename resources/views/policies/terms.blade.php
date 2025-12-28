@extends('layouts.app')

@section('content')
@php
    /** @var \App\Models\SiteSetting|null $site */
    $site = \App\Models\SiteSetting::query()->first();
    $policyHtml = trim((string) ($site?->terms_of_service ?? ''));
    $supportEmail = $site?->support_email ?? 'support@dispatch.store';
@endphp
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-4xl font-bold text-gray-900 mb-8">Terms of Service</h1>

    <div class="prose prose-lg max-w-none space-y-6 text-gray-700">
        @if($policyHtml !== '')
            <div class="space-y-4 text-gray-700 leading-7" {!! 'v-pre' !!}>
                {!! $policyHtml !!}
            </div>
        @else
            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Acceptance of Terms</h2>
                <p class="text-base">
                    By using our platform, you agree to these Terms of Service. If you do not agree, please do not use our site.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">2. Business Model</h2>
                <p class="text-base">
                    We operate a <strong>dropshipping fulfillment model</strong>. This means:
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>Products are fulfilled by third-party suppliers, not from our own warehouse.</li>
                    <li>Delivery times and product availability depend on supplier stock.</li>
                    <li>You may receive packages from different warehouses or countries.</li>
                    <li>We are not liable for supplier errors, though we will facilitate resolution.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Product Information</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>Product descriptions, images, and prices are accurate to the best of our knowledge.</li>
                    <li>Prices may change without notice.</li>
                    <li>We reserve the right to discontinue products at any time.</li>
                    <li>Availability is not guaranteed at the time of purchase.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Orders & Payment</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>All orders are subject to acceptance and verification.</li>
                    <li>We may refuse or cancel any order for any reason.</li>
                    <li>Payment must be received before order processing begins.</li>
                    <li>Fraudulent orders will be canceled and reported to authorities.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Shipping & Delivery</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li><strong>Delivery times are estimates, not guarantees.</strong></li>
                    <li>You are responsible for providing an accurate shipping address.</li>
                    <li>Customs duties and import taxes are your responsibility.</li>
                    <li>We are not liable for packages lost or damaged by carriers.</li>
                    <li>Risk of loss transfers to you upon shipment from our supplier.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">6. User Accounts</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
                    <li>You agree to provide accurate and current information.</li>
                    <li>We may suspend accounts that violate our policies.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Limitation of Liability</h2>
                <p class="text-base">
                    We are not liable for indirect, incidental, or consequential damages. Our total liability is limited to the order amount.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Dispute Resolution</h2>
                <p class="text-base">
                    Disputes are resolved through:
                </p>
                <ol class="list-decimal list-inside space-y-2 mt-4">
                    <li>Customer support inquiry</li>
                    <li>Formal complaint process</li>
                    <li>Payment provider arbitration (if applicable)</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Modifications</h2>
                <p class="text-base">
                    We may update these terms at any time. Continued use of the platform constitutes acceptance of updated terms.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">10. Contact Us</h2>
                <p class="text-base">
                    For questions about these terms, contact us at <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:underline">{{ $supportEmail }}</a>.
                </p>
            </section>
        @endif
    </div>
</div>
@endsection
