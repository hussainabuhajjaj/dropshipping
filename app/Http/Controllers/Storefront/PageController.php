<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function about(): Response
    {
        $settings = SiteSetting::first();
        
        return Inertia::render('About/Index', [
            'content' => $settings?->about_page_html ?? '<p>About page coming soon...</p>',
            'pageTitle' => 'About Us',
            'pageDescription' => 'Learn more about our story, mission, and values.',
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
            'pageTitle' => 'Contact Us',
            'pageDescription' => 'Get in touch with our customer support team.',
        ]);
    }

    public function shippingPolicy(): Response
    {
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->shipping_policy ?? '<p>Shipping policy coming soon...</p>',
            'pageTitle' => 'Shipping Policy',
            'pageDescription' => 'Learn about our shipping process, delivery times, and costs.',
        ]);
    }

    public function refundPolicy(): Response
    {
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->refund_policy ?? '<p>Refund policy coming soon...</p>',
            'pageTitle' => 'Refund & Return Policy',
            'pageDescription' => 'Understand our return and refund procedures.',
        ]);
    }

    public function privacyPolicy(): Response
    {
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->privacy_policy ?? '<p>Privacy policy coming soon...</p>',
            'pageTitle' => 'Privacy Policy',
            'pageDescription' => 'Learn how we collect, use, and protect your personal information.',
        ]);
    }

    public function termsOfService(): Response
    {
        $settings = SiteSetting::first();
        
        return Inertia::render('Legal/Policy', [
            'content' => $settings?->terms_of_service ?? '<p>Terms of service coming soon...</p>',
            'pageTitle' => 'Terms of Service',
            'pageDescription' => 'Read our terms and conditions for using our services.',
        ]);
    }
}
