@extends('layouts.app')

@section('content')
@php
    /** @var \App\Models\SiteSetting|null $site */
    $site = \App\Models\SiteSetting::query()->first();
    $supportEmail = $site?->support_email ?? 'support@dispatch.store';
    $policyHtml = trim((string) ($site?->shipping_policy ?? ''));
@endphp
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-4xl font-bold text-gray-900 mb-8">Shipping Policy</h1>

    <div class="prose prose-lg max-w-none space-y-6 text-gray-700">
        @if($policyHtml !== '')
            <div class="space-y-4 text-gray-700 leading-7" {!! 'v-pre' !!}>
                {!! $policyHtml !!}
            </div>
        @else
            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Delivery Times</h2>
                <p class="text-base">
                    <strong>Delivery times are estimates and not guaranteed.</strong> Most orders ship within 3-10 business days from the time of payment confirmation, depending on product availability and your destination country.
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li><strong>Processing time:</strong> 1-3 business days</li>
                    <li><strong>Shipping time:</strong> 5-15 business days (international)</li>
                    <li><strong>Total estimate:</strong> 7-20 business days from order placement</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">How Your Order Ships</h2>
                <p class="text-base">
                    We operate on a dropshipping fulfillment model. Your order is fulfilled directly from our supplier's warehouse and shipped to your address. This means:
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>You receive tracking information via email once the package ships.</li>
                    <li>Shipments may originate from different warehouses or countries.</li>
                    <li>You can track your package status on your order page.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Customs & Import Duties</h2>
                <p class="text-base">
                    <strong>Customs duties, taxes, and import fees may apply.</strong> These are the responsibility of the customer and are determined by your country's customs regulations.
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>We are not responsible for customs delays or additional fees.</li>
                    <li>Your order value is declared accurately on the package.</li>
                    <li>Contact your local customs office for specific duty information.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">International Shipping</h2>
                <p class="text-base">
                    We ship to most countries worldwide. Delivery speed may vary based on destination and carrier capacity.
                </p>
                <ul class="list-disc list-inside space-y-2 mt-4">
                    <li>Packages are shipped via courier (DHL, FedEx, UPS, or local carriers).</li>
                    <li>Remote areas may take longer to deliver.</li>
                    <li>Rural addresses may incur additional shipping fees.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">What If My Package Is Lost?</h2>
                <p class="text-base">
                    In rare cases, packages may be lost during transit. We will:
                </p>
                <ol class="list-decimal list-inside space-y-2 mt-4">
                    <li>Investigate the shipment status with the carrier.</li>
                    <li>Provide compensation or replacement if the carrier is at fault.</li>
                    <li>Contact you within 10 business days of the loss confirmation.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Need Help?</h2>
                <p class="text-base">
                    If you have questions about your shipment, contact us at <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:underline">{{ $supportEmail }}</a> with your order number and tracking reference.
                </p>
            </section>
        @endif
    </div>
</div>
@endsection
