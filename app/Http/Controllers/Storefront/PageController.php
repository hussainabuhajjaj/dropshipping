<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function about(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();
        
        return Inertia::render('About/Index', [
            'content' => $settings?->localizedValue('about_page_html', $locale) ?? '<p>About page coming soon...</p>',
            'pageTitle' => __('About Us'),
            'pageDescription' => __('Learn more about our story, mission, and values.'),
        ]);
    }

    public function contact(): Response
    {
        $settings = SiteSetting::first();
        
        return Inertia::render('Contact/Index', [
            'supportEmail' => $settings?->support_email,
            'supportWhatsapp' => $settings?->support_whatsapp,
            'supportPhone' => $settings?->support_phone,
            'supportHours' => $settings?->support_hours,
            'pageTitle' => __('Contact Us'),
            'pageDescription' => __('Get in touch with our customer support team.'),
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $settings = SiteSetting::first();
        $supportEmail = $settings?->support_email ?: config('mail.from.address');

        if (! $supportEmail) {
            return back()->withErrors([
                'contact' => 'Support email is not configured. Please try again later.',
            ]);
        }

        try {
            Mail::to($supportEmail)->send(new ContactMessageMail($data));
        } catch (\Throwable $exception) {
            Log::error('Contact form send failed', [
                'error' => $exception->getMessage(),
                'email' => $data['email'] ?? null,
            ]);

            return back()->withErrors([
                'contact' => 'We could not send your message right now. Please try again later.',
            ]);
        }

        return back()->with('contact_notice', 'Thanks! Your message has been sent.');
    }

    public function shippingPolicy(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->localizedValue('shipping_policy', $locale) ?? '<p>Shipping policy coming soon...</p>',
            'pageTitle' => __('Shipping Policy'),
            'pageDescription' => __('Learn about our shipping process, delivery times, and costs.'),
        ]);
    }

    public function refundPolicy(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->localizedValue('refund_policy', $locale) ?? '<p>Refund policy coming soon...</p>',
            'pageTitle' => __('Refund Policy'),
            'pageDescription' => __('Understand our return and refund procedures.'),
        ]);
    }

    public function privacyPolicy(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->localizedValue('privacy_policy', $locale) ?? '<p>Privacy policy coming soon...</p>',
            'pageTitle' => __('Privacy Policy'),
            'pageDescription' => __('Learn how we collect, use, and protect your personal information.'),
        ]);
    }

    public function termsOfService(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->localizedValue('terms_of_service', $locale) ?? '<p>Terms of service coming soon...</p>',
            'pageTitle' => __('Terms of Service'),
            'pageDescription' => __('Read our terms and conditions for using our services.'),
        ]);
    }

    public function customsDisclaimer(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();

        return Inertia::render('Legal/CustomsDisclaimer', [
            'policyHtml' => $settings?->localizedValue('customs_disclaimer', $locale) ?? $settings?->customs_disclaimer ?? '',
            'supportEmail' => $settings?->support_email,
        ]);
    }
}
