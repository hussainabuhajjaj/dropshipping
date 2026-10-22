@extends('layouts.app')

@section('content')
@php
    /** @var \App\Models\SiteSetting|null $site */
    $site = \App\Models\SiteSetting::query()->first();
    $policyHtml = trim((string) ($site?->privacy_policy ?? ''));
    $supportEmail = $site?->support_email ?? 'support@dispatch.store';
@endphp
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-4xl font-bold text-gray-900 mb-8">Privacy Policy</h1>

    <div class="prose prose-lg max-w-none space-y-6 text-gray-700">
        @if($policyHtml !== '')
            <div class="space-y-4 text-gray-700 leading-7" {!! 'v-pre' !!}>
                {!! $policyHtml !!}
            </div>
        @else
            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">1. Information We Collect</h2>
                <p class="text-base">
                    We collect information to process orders and improve your experience:
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li><strong>Account info:</strong> Name, email, phone number</li>
                    <li><strong>Shipping address:</strong> For order delivery</li>
                    <li><strong>Payment info:</strong> Processed securely by payment providers (we don't store card details)</li>
                    <li><strong>Usage data:</strong> Pages visited, products viewed, time spent</li>
                    <li><strong>Device info:</strong> Browser, IP address, device type</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">2. How We Use Your Information</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>Process and fulfill your orders</li>
                    <li>Send order updates and shipping notifications</li>
                    <li>Respond to customer support requests</li>
                    <li>Detect and prevent fraud</li>
                    <li>Improve our products and services</li>
                    <li>Send marketing emails (with your consent)</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">3. Data Sharing</h2>
                <p class="text-base">
                    We may share your information with:
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li><strong>Suppliers:</strong> Shipping address and name for order fulfillment</li>
                    <li><strong>Payment providers:</strong> To process payments securely</li>
                    <li><strong>Shipping carriers:</strong> To deliver your package</li>
                    <li><strong>Analytics services:</strong> For anonymized performance metrics</li>
                    <li><strong>Legal authorities:</strong> If required by law</li>
                </ul>
                <p class="text-base mt-4">
                    We do not sell your personal data to third parties.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">4. Data Security</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>We use SSL encryption to protect data in transit.</li>
                    <li>Access to personal data is restricted to authorized employees.</li>
                    <li>We cannot guarantee 100% security, but take reasonable precautions.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">5. Your Rights</h2>
                <p class="text-base">
                    You have the right to:
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>Access your personal data</li>
                    <li>Request corrections or deletions</li>
                    <li>Opt out of marketing emails</li>
                    <li>Request data portability</li>
                </ul>
                <p class="text-base mt-4">
                    Contact us at <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:underline">{{ $supportEmail }}</a> to exercise these rights.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">6. Cookies & Tracking</h2>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>We use cookies to remember your preferences and improve experience.</li>
                    <li>You can disable cookies in your browser settings.</li>
                    <li>Third-party analytics may use tracking technologies.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">7. Children's Privacy</h2>
                <p class="text-base">
                    Our platform is not intended for users under 18. We do not knowingly collect data from children.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">8. Policy Updates</h2>
                <p class="text-base">
                    We may update this policy periodically. Continued use constitutes acceptance.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Contact</h2>
                <p class="text-base">
                    Questions about this policy? Email us at <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:underline">{{ $supportEmail }}</a>.
                </p>
            </section>
        @endif
    </div>
</div>
@endsection
