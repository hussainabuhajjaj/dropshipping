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

    public function cookiePolicy(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();
        $content = $settings?->localizedValue('cookie_policy', $locale)
            ?? $settings?->cookie_policy
            ?? '<p>Cookie policy coming soon...</p>';

        return Inertia::render('Legal/Policy', [
            'content' => $content,
            'pageTitle' => __('Cookie Policy'),
            'pageDescription' => __('Understand which cookies we use and how consent choices affect your storefront experience.'),
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

    public function userDataDeletion(): Response
    {
        $locale = app()->getLocale();
        $settings = SiteSetting::first();
        $supportEmail = $settings?->support_email ?: config('mail.from.address') ?: 'support@simbazu.net';

        if ($locale === 'fr') {
            $content = <<<HTML
<p>Cette page explique comment demander la suppression de votre compte et de vos donnees personnelles associees a Simbazu.</p>
<h2>Supprimer vos donnees depuis l'application</h2>
<ol>
  <li>Connectez-vous a votre compte dans l'application Simbazu.</li>
  <li>Ouvrez <strong>Parametres</strong>.</li>
  <li>Choisissez <strong>Supprimer le compte</strong> et confirmez la demande.</li>
</ol>
<h2>Si vous ne pouvez pas vous connecter</h2>
<p>Envoyez une demande de suppression de donnees a <a href="mailto:{$supportEmail}">{$supportEmail}</a> depuis l'adresse e-mail liee a votre compte ou en mentionnant cette adresse e-mail dans votre message.</p>
<h2>Ce qui est supprime</h2>
<ul>
  <li>Votre profil client et vos donnees personnelles de compte.</li>
  <li>Les jetons de connexion et l'acces a l'application.</li>
  <li>Les donnees marketing non obligatoires liees a votre compte, lorsque cela est applicable.</li>
</ul>
<h2>Ce qui peut etre conserve</h2>
<p>Certaines donnees de commande, de paiement, de prevention de fraude ou de support peuvent etre conservees pendant la duree imposee par la loi, la comptabilite, les litiges ou la securite.</p>
<h2>Delai de traitement</h2>
<p>Nous traitons generalement les demandes de suppression dans un delai de 7 jours ouvrables, sous reserve d'une verification raisonnable de la propriete du compte.</p>
HTML;

            return Inertia::render('Legal/Policy', [
                'content' => $content,
                'pageTitle' => __('Suppression des donnees utilisateur'),
                'pageDescription' => __("Comment supprimer votre compte Simbazu et demander l'effacement de vos donnees personnelles."),
            ]);
        }

        $content = <<<HTML
<p>This page explains how you can request deletion of your Simbazu account and associated personal data.</p>
<h2>Delete your data from the app</h2>
<ol>
  <li>Sign in to your account in the Simbazu app.</li>
  <li>Open <strong>Settings</strong>.</li>
  <li>Select <strong>Delete account</strong> and confirm the request.</li>
</ol>
<h2>If you cannot sign in</h2>
<p>Send a deletion request to <a href="mailto:{$supportEmail}">{$supportEmail}</a> from the email address linked to your account, or mention that email address in your message.</p>
<h2>What gets deleted</h2>
<ul>
  <li>Your customer profile and account personal data.</li>
  <li>Your active login tokens and access to the app.</li>
  <li>Non-essential marketing data linked to your account, where applicable.</li>
</ul>
<h2>What may be retained</h2>
<p>Some order, payment, fraud-prevention, and support records may be retained for legal, accounting, dispute-resolution, or security obligations.</p>
<h2>Processing time</h2>
<p>We generally process deletion requests within 7 business days, subject to reasonable account ownership verification.</p>
HTML;

        return Inertia::render('Legal/Policy', [
            'content' => $content,
            'pageTitle' => __('User Data Deletion'),
            'pageDescription' => __('How to delete your Simbazu account and request erasure of your personal data.'),
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
