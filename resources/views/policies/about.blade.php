@extends('layouts.app')

@section('content')
@php
    /** @var \App\Models\SiteSetting|null $site */
    $site = \App\Models\SiteSetting::query()->first();
    $supportEmail = $site?->support_email ?? 'support@dispatch.store';
    $aboutHtml = trim((string) ($site?->about_page_html ?? ''));
@endphp
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-4xl font-bold text-gray-900 mb-8">About Us & Contact</h1>

    <div class="prose prose-lg max-w-none space-y-6 text-gray-700">
        @if($aboutHtml !== '')
            <div class="space-y-4 text-gray-700 leading-7" {!! 'v-pre' !!}>
                {!! $aboutHtml !!}
            </div>
        @else
        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">About Us</h2>
            <p class="text-base">
                We are a global e-commerce platform connecting customers with quality products through trusted suppliers worldwide.
            </p>
            <p class="text-base mt-4">
                Our mission is to make international shopping simple, affordable, and reliable. We operate using a dropshipping fulfillment model, meaning products are shipped directly from suppliers to your doorstep.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Business Model</h2>
            <p class="text-base">
                We specialize in dropshipping, which allows us to:
            </p>
            <ul class="list-disc list-inside space-y-2 mt-4">
                <li>Offer products without maintaining large warehouses</li>
                <li>Provide competitive pricing</li>
                <li>Reduce environmental impact through direct shipping</li>
                <li>Quickly adapt to market demand</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Suppliers</h2>
            <p class="text-base">
                We partner with vetted suppliers like CJ Dropshipping, ensuring:
            </p>
            <ul class="list-disc list-inside space-y-2 mt-4">
                <li>Product quality and authenticity</li>
                <li>Reliable shipping and tracking</li>
                <li>Fast processing and fulfillment</li>
                <li>Professional customer support</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Get in Touch</h2>
            <p class="text-base mb-6">
                Have questions? We're here to help! Reach out using any of these methods:
            </p>

            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 space-y-4">
                <div>
                    <h3 class="font-bold text-gray-900">Email</h3>
                    <p class="text-base">
                        <a href="mailto:{{ $supportEmail }}" class="text-blue-600 hover:underline">{{ $supportEmail }}</a>
                    </p>
                    <p class="text-sm text-gray-600">Response time: 24-48 hours</p>
                </div>

                <div>
                    <h3 class="font-bold text-gray-900">Contact Form</h3>
                    <p class="text-base">
                        Visit our <a href="/support" class="text-blue-600 hover:underline">support page</a> to send a message.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-gray-900">Order Support</h3>
                    <p class="text-base">
                        Include your order number when inquiring about a specific order.
                    </p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Hours of Operation</h2>
            <p class="text-base">
                Our support team works during business hours. We aim to respond to all inquiries within 24-48 hours.
            </p>
        </section>

        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Community & Feedback</h2>
            <p class="text-base">
                We value your feedback! Share your experience by:
            </p>
            <ul class="list-disc list-inside space-y-2 mt-4">
                <li>Leaving product reviews</li>
                <li>Rating your experience</li>
                <li>Contacting us with suggestions for improvement</li>
            </ul>
        </section>

        <section class="bg-blue-50 p-6 rounded-lg border border-blue-200 mt-8">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Questions About Our Policies?</h2>
            <p class="text-base text-blue-800">
                Learn more about how we operate by visiting our:
            </p>
            <ul class="list-disc list-inside space-y-2 mt-4 text-blue-800">
                <li><a href="/legal/shipping-policy" class="underline hover:no-underline">Shipping Policy</a></li>
                <li><a href="/legal/refund-policy" class="underline hover:no-underline">Refund & Return Policy</a></li>
                <li><a href="/legal/terms-of-service" class="underline hover:no-underline">Terms of Service</a></li>
                <li><a href="/legal/privacy-policy" class="underline hover:no-underline">Privacy Policy</a></li>
            </ul>
        </section>
        @endif
    </div>
</div>
@endsection
