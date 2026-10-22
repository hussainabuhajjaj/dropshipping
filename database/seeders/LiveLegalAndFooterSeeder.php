<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Models\StorefrontSetting;
use Illuminate\Database\Seeder;

class LiveLegalAndFooterSeeder extends Seeder
{
    public function run(): void
    {
        $site = SiteSetting::query()->first() ?? new SiteSetting();

        $siteName = (string) ($site->site_name ?: 'Simbazu');
        $supportEmail = (string) ($site->support_email ?: 'info@simbazu.net');
        $supportWhatsapp = (string) ($site->support_whatsapp ?: '+22500000000');
        $supportPhone = (string) ($site->support_phone ?: '+22500000000');
        $supportHours = (string) ($site->support_hours ?: 'Mon-Sat, 09:00-18:00 GMT');
        $today = now()->toDateString();

        $waDigits = preg_replace('/\D+/', '', $supportWhatsapp) ?: '';
        $waLink = $waDigits !== '' ? "https://wa.me/{$waDigits}" : '/support';

        $site->fill([
            'site_name' => $siteName,
            'site_description' => $site->site_description ?: "Cross-border shopping made clear for customers in Cote d'Ivoire.",
            'support_email' => $supportEmail,
            'support_whatsapp' => $supportWhatsapp,
            'support_phone' => $supportPhone,
            'support_hours' => $supportHours,
            'about_page_html' => $this->aboutHtml($siteName, $supportEmail, $supportPhone, $supportWhatsapp, $supportHours, $waLink),
            'privacy_policy' => $this->privacyHtml($siteName, $supportEmail, $today),
            'terms_of_service' => $this->termsHtml($siteName, $supportEmail, $today),
            'shipping_policy' => $this->shippingPolicyHtml($siteName, $supportEmail, $today),
            'refund_policy' => $this->refundPolicyHtml($siteName, $supportEmail, $today),

        ]);
        $site->save();

        $storefront = StorefrontSetting::query()->whereNull('locale')->latest()->first() ?? new StorefrontSetting();
        $storefront->fill([
            'locale' => null,
            'brand_name' => $storefront->brand_name ?: $siteName,
            'footer_blurb' => 'Global sourcing with local clarity. Transparent duties, tracked delivery, and responsive support.',
            'delivery_notice' => "Delivery to Cote d'Ivoire with duties shown before checkout.",
            'copyright_text' => $siteName,
            'header_links' => [
                ['label' => 'Shop', 'href' => '/products'],
                ['label' => 'Collections', 'href' => '/collections'],
                ['label' => 'Promotions', 'href' => '/promotions'],
                ['label' => 'Track Order', 'href' => '/orders/track'],
                ['label' => 'Support', 'href' => '/support'],
                ['label' => 'FAQ', 'href' => '/faq'],
            ],
            'footer_columns' => [
                [
                    'title' => 'Shop',
                    'links' => [
                        ['label' => 'All products', 'href' => '/products'],
                        ['label' => 'Collections', 'href' => '/collections'],
                        ['label' => 'Promotions', 'href' => '/promotions'],
                        ['label' => 'Track order', 'href' => '/orders/track'],
                    ],
                ],
                [
                    'title' => 'Support',
                    'links' => [
                        ['label' => 'Support center', 'href' => '/support'],
                        ['label' => 'Contact us', 'href' => '/contact'],
                        ['label' => 'FAQ', 'href' => '/faq'],
                        ['label' => 'Refund requests', 'href' => '/legal/refund-policy'],
                        ['label' => 'WhatsApp', 'href' => $waLink],
                        ['label' => 'Email support', 'href' => "mailto:{$supportEmail}"],
                    ],
                ],
                [
                    'title' => 'Company',
                    'links' => [
                        ['label' => 'About us', 'href' => '/about'],
                        ['label' => 'Contact', 'href' => '/contact'],
                        ['label' => 'Support', 'href' => '/support'],
                    ],
                ],
                [
                    'title' => 'Legal',
                    'links' => [
                        ['label' => 'Shipping policy', 'href' => '/legal/shipping-policy'],
                        ['label' => 'Refund policy', 'href' => '/legal/refund-policy'],
                        ['label' => 'Terms of service', 'href' => '/legal/terms-of-service'],
                        ['label' => 'Privacy policy', 'href' => '/legal/privacy-policy'],
                        ['label' => 'Customs disclaimer', 'href' => '/legal/customs-disclaimer'],
                    ],
                ],
            ],
            'social_links' => [
                ['label' => 'Email', 'href' => "mailto:{$supportEmail}", 'icon' => 'mail'],
                ['label' => 'WhatsApp', 'href' => $waLink, 'icon' => 'whatsapp'],
                ['label' => 'Support', 'href' => '/support', 'icon' => 'help-circle'],
            ],
        ]);
        $storefront->save();
    }



    private function aboutHtml(
        string $siteName,
        string $supportEmail,
        string $supportPhone,
        string $supportWhatsapp,
        string $supportHours,
        string $waLink
    ): string {
        $siteNameEsc = e($siteName);

        return <<<HTML
<div class="prose max-w-4xl mx-auto">
  <h1>About {$siteNameEsc}</h1>

  <p><strong>{$siteNameEsc}</strong> is a cross-border shopping platform built for customers in Côte d’Ivoire. We help you discover products, place orders with clear totals, and track delivery—while keeping support simple and responsive.</p>

  <h2>What We Offer</h2>
  <ul>
    <li><strong>Curated products</strong> from international suppliers and marketplaces.</li>
    <li><strong>Clear checkout</strong> with transparent pricing and order confirmations.</li>
    <li><strong>Delivery tracking</strong> where tracking is available from carriers/partners.</li>
    <li><strong>Support</strong> for order updates, issues, returns, and refunds.</li>
  </ul>

  <h2>How Cross-Border Orders Work</h2>
  <ul>
    <li><strong>Availability:</strong> Stock and prices can change quickly. If an item becomes unavailable after purchase, we will offer an alternative or a refund according to our policies.</li>
    <li><strong>Shipping timelines:</strong> Estimated delivery windows depend on supplier processing, carrier performance, and local delivery conditions. Estimates are not guarantees.</li>
    <li><strong>Customs & duties:</strong> For some orders, customs duties/taxes may apply depending on local regulations. Where we can estimate or pre-disclose these charges, we show them before payment. In other cases, charges may be collected by the carrier or customs authorities at delivery.</li>
    <li><strong>Product variations:</strong> Colors, packaging, and minor product details may vary by supplier or batch.</li>
  </ul>

  <h2>Our Standards</h2>
  <ul>
    <li><strong>Accuracy:</strong> We aim to keep product content, prices, and availability updated. If any information is incorrect, contact us and we will investigate.</li>
    <li><strong>Security:</strong> We use security measures to protect customer accounts, payments, and platform access.</li>
    <li><strong>Fair resolution:</strong> We follow our shipping/refund policies to resolve issues consistently.</li>
  </ul>

  <h2>Contact</h2>
  <ul>
    <li>Email: <a href="mailto:{$supportEmail}">{$supportEmail}</a></li>
    <li>WhatsApp: <a href="{$waLink}" target="_blank" rel="noopener noreferrer">{$supportWhatsapp}</a></li>
    <li>Phone: <a href="tel:{$supportPhone}">{$supportPhone}</a></li>
    <li>Hours: {$supportHours}</li>
  </ul>

  <h2>Help & Legal</h2>
  <ul>
    <li><a href="/support">Support Center</a></li>
    <li><a href="/faq">FAQ</a></li>
    <li><a href="/orders/track">Track Order</a></li>
    <li><a href="/legal/shipping-policy">Shipping Policy</a></li>
    <li><a href="/legal/refund-policy">Refund Policy</a></li>
    <li><a href="/legal/terms-of-service">Terms of Service</a></li>
    <li><a href="/legal/privacy-policy">Privacy Policy</a></li>
    <li><a href="/legal/customs-disclaimer">Customs Disclaimer</a></li>
  </ul>
</div>
HTML;
    }

    private function privacyHtml(string $siteName, string $supportEmail, string $today): string
    {
        $siteNameEsc = e($siteName);

        return <<<HTML
<div class="prose max-w-4xl mx-auto">
  <h1>Privacy Policy</h1>
  <p><strong>Last updated:</strong> {$today}</p>

  <p>This Privacy Policy explains how <strong>{$siteNameEsc}</strong> collects, uses, shares, and protects personal information when you use our website, storefront, and support services.</p>

  <h2>1. Information We Collect</h2>
  <ul>
    <li><strong>Account & contact:</strong> name, email, phone number, login identifiers.</li>
    <li><strong>Order & delivery:</strong> shipping address, order items, delivery preferences, tracking details, and support communications.</li>
    <li><strong>Payment context:</strong> payment status and transaction references from payment providers. <strong>We do not store full card numbers</strong> or sensitive payment credentials.</li>
    <li><strong>Device & usage:</strong> IP address, device type, browser, pages viewed, actions taken, timestamps, and basic analytics.</li>
    <li><strong>Customer support:</strong> messages, attachments you send, and resolution notes.</li>
  </ul>

  <h2>2. Why We Use Your Data</h2>
  <ul>
    <li>To create and manage your account and orders.</li>
    <li>To process payments, prevent fraud, and secure the platform.</li>
    <li>To ship and deliver products and provide tracking.</li>
    <li>To communicate updates (order confirmation, shipping, refunds, service notices).</li>
    <li>To provide customer support and handle disputes.</li>
    <li>To improve products, performance, and user experience.</li>
    <li>To comply with legal obligations (tax, accounting, anti-fraud, and lawful requests).</li>
  </ul>

  <h2>3. Legal Bases</h2>
  <p>We process data where necessary to perform a contract (fulfill orders), comply with legal obligations, protect our legitimate interests (security, fraud prevention, service improvement), and where applicable, based on your consent (e.g., marketing emails where required).</p>

  <h2>4. Sharing & Disclosure</h2>
  <p>We share information only as needed to operate the service:</p>
  <ul>
    <li><strong>Logistics partners:</strong> carriers, freight forwarders, and delivery providers to deliver your order.</li>
    <li><strong>Payment providers:</strong> to process transactions and confirm payment status.</li>
    <li><strong>Infrastructure & tools:</strong> hosting, analytics, error logging, customer support platforms.</li>
    <li><strong>Legal requirements:</strong> where required by law, regulation, court order, or to protect rights and safety.</li>
  </ul>

  <h2>5. International Transfers</h2>
  <p>Because we operate cross-border, your data may be processed in countries outside Côte d’Ivoire. When this happens, we apply reasonable safeguards appropriate for our operations and service providers.</p>

  <h2>6. Data Retention</h2>
  <p>We retain personal data only as long as needed for order fulfillment, support, legal compliance, fraud prevention, and dispute handling. Retention periods may vary depending on the type of data and applicable laws.</p>

  <h2>7. Security</h2>
  <p>We use administrative, technical, and organizational measures to protect personal data. No system is 100% secure, so we cannot guarantee absolute security.</p>

  <h2>8. Your Rights</h2>
  <p>Depending on your location and applicable law, you may request access, correction, deletion, or portability of your personal data, and you may object to certain processing. You can submit requests at <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>

  <h2>9. Cookies & Similar Technologies</h2>
  <p>We use cookies and similar technologies for authentication, cart/checkout continuity, analytics, and security. You can control cookies through your browser settings, but some features may not work properly.</p>

  <h2>10. Marketing Communications</h2>
  <p>If you subscribe to marketing messages, you can unsubscribe at any time using the link in our emails or by contacting support. Transactional messages (order updates) may still be sent as needed to fulfill your orders.</p>

  <h2>11. Children</h2>
  <p>Our service is not intended for children. If you believe a child has provided personal data, contact us so we can take appropriate action.</p>

  <h2>12. Contact</h2>
  <p>Privacy requests and questions: <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
</div>
HTML;
    }

    private function termsHtml(string $siteName, string $supportEmail, string $today): string
    {
        $siteNameEsc = e($siteName);

        return <<<HTML
<div class="prose max-w-4xl mx-auto">
  <h1>Terms of Service</h1>
  <p><strong>Effective date:</strong> {$today}</p>

  <p>These Terms govern your access to and use of <strong>{$siteNameEsc}</strong> (the “Service”), including browsing, purchases, payments, delivery, and customer support. By using the Service, you agree to these Terms.</p>

  <h2>1. Eligibility & Accounts</h2>
  <ul>
    <li>You must provide accurate account and delivery information.</li>
    <li>You are responsible for safeguarding your login credentials and all activity under your account.</li>
    <li>We may suspend accounts involved in fraud, abuse, or violations of these Terms.</li>
  </ul>

  <h2>2. Products, Content & Availability</h2>
  <ul>
    <li>Product images and descriptions are provided by suppliers and may contain minor inaccuracies.</li>
    <li>Availability can change quickly. If an item becomes unavailable after purchase, we may offer a replacement, partial fulfillment, or refund according to our policies.</li>
    <li>We may limit quantities, restrict certain items, or refuse orders to prevent fraud or comply with laws.</li>
  </ul>

  <h2>3. Pricing, Currency & Taxes</h2>
  <ul>
    <li>Displayed prices may change before checkout. The checkout total and order confirmation control.</li>
    <li>Where available, duties/taxes may be displayed before payment. In other cases, customs charges may be collected by authorities or carriers at delivery.</li>
    <li>Promotions and coupon rules (eligibility, minimum order, expiry) are shown at checkout or on the promotion page.</li>
  </ul>

  <h2>4. Payments</h2>
  <ul>
    <li>Payments are processed through third-party providers. We do not store full card details.</li>
    <li>Orders may be held for verification to prevent fraud.</li>
    <li>If a payment fails or is reversed, we may cancel the order or pause fulfillment.</li>
  </ul>

  <h2>5. Shipping & Delivery</h2>
  <ul>
    <li>Delivery estimates are not guarantees and depend on supplier processing, carriers, and local conditions.</li>
    <li>Tracking may be limited for some routes or handoffs; we provide the best available tracking information.</li>
    <li>Incorrect addresses or unreachable recipients may cause delays, additional fees, or returns.</li>
  </ul>

  <h2>6. Returns, Refunds & Chargebacks</h2>
  <ul>
    <li>Returns and refunds are governed by our <a href="/legal/refund-policy">Refund Policy</a>.</li>
    <li>Refund timelines depend on payment providers and banking networks.</li>
    <li>Chargebacks without contacting support may delay resolution; we encourage you to contact support first.</li>
  </ul>

  <h2>7. Acceptable Use</h2>
  <p>You agree not to:</p>
  <ul>
    <li>Misuse the Service, attempt unauthorized access, or interfere with its operation.</li>
    <li>Scrape protected content, reverse engineer, or abuse APIs without permission.</li>
    <li>Submit false claims, engage in fraud, or violate applicable laws.</li>
  </ul>

  <h2>8. Intellectual Property</h2>
  <p>All website content, trademarks, designs, and software are owned by {$siteNameEsc} or licensed to us. You may not use them without permission, except as allowed by law.</p>

  <h2>9. Disclaimer of Warranties</h2>
  <p>The Service is provided “as is” and “as available.” We do not guarantee uninterrupted access, exact delivery times, or that product information is error-free. Where warranties cannot be excluded, they are limited to the extent permitted by law.</p>

  <h2>10. Limitation of Liability</h2>
  <p>To the maximum extent permitted by law, {$siteNameEsc} will not be liable for indirect, incidental, or consequential damages. Our total liability for claims relating to an order is limited to the amount you paid for the applicable order, except where the law does not allow such limits.</p>

  <h2>11. Indemnity</h2>
  <p>You agree to indemnify and hold {$siteNameEsc} harmless from claims arising out of your misuse of the Service, violation of these Terms, or violation of any law or third-party rights.</p>

  <h2>12. Changes to the Service or Terms</h2>
  <p>We may modify or discontinue parts of the Service and update these Terms to reflect changes in operations, security, or legal requirements. Updated Terms apply from the time they are posted.</p>

  <h2>13. Governing Law & Disputes</h2>
  <p>These Terms are governed by applicable laws. We encourage customers to contact support first to resolve disputes quickly and fairly.</p>

  <h2>14. Contact</h2>
  <p>Questions about these Terms: <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
</div>
HTML;
    }
    private function shippingPolicyHtml(string $siteName, string $supportEmail, string $today): string
{
    $siteNameEsc = e($siteName);

    return <<<HTML
<div class="prose max-w-4xl mx-auto">
  <h1>Shipping Policy</h1>
  <p><strong>Last updated:</strong> {$today}</p>

  <p>This Shipping Policy explains how deliveries work on <strong>{$siteNameEsc}</strong>, including processing times, shipping estimates, tracking, and customs considerations.</p>

  <h2>1. Delivery Coverage</h2>
  <p>We currently deliver to Côte d’Ivoire and selected regions depending on the product, carrier availability, and destination address.</p>

  <h2>2. Processing Time</h2>
  <p>Orders are usually processed within <strong>1–5 business days</strong>. Processing includes payment confirmation, supplier preparation, packing, and dispatch.</p>
  <p>During high-demand periods (Black Friday, holidays, promotions), processing may take longer.</p>

  <h2>3. Estimated Delivery Time</h2>
  <p>Delivery estimates shown on the website are approximate and not guaranteed. Typical delivery timeframes range from:</p>
  <ul>
    <li><strong>7–25 business days</strong> for standard international shipping</li>
    <li><strong>5–15 business days</strong> for priority routes (when available)</li>
  </ul>
  <p>Delivery may be affected by customs clearance, carrier delays, weather, local disruptions, or incomplete address details.</p>

  <h2>4. Shipping Fees</h2>
  <p>Shipping fees (if applicable) are shown at checkout. Some products may qualify for free shipping promotions based on cart value or campaign rules.</p>

  <h2>5. Tracking</h2>
  <p>When tracking is available, tracking details will be shared by email or inside your account. Tracking may update slowly depending on carrier systems and international handovers.</p>

  <h2>6. Customs Duties & Taxes</h2>
  <p>Some orders may be subject to customs duties, VAT, inspection fees, or clearance charges depending on local regulations. Where we can estimate these charges, they may be displayed before checkout.</p>
  <p>In some cases, customs fees may be requested by the carrier or customs authorities at delivery. These charges are outside of our direct control.</p>

  <h2>7. Incorrect Address / Delivery Failure</h2>
  <p>Customers are responsible for providing accurate delivery information. If an order cannot be delivered due to an incorrect address, unreachable recipient, or refusal of delivery, the order may be returned or abandoned by the carrier.</p>
  <p>In such cases, additional fees may apply and refunds may be partial depending on shipping and handling costs.</p>

  <h2>8. Lost, Delayed, or Missing Packages</h2>
  <p>If your tracking shows no movement for an extended period or the package is marked as delivered but you did not receive it, contact our support team immediately.</p>
  <p>We will investigate with the carrier and supplier. Resolution may include reshipment, store credit, or refund depending on the investigation outcome.</p>

  <h2>9. Damaged Items</h2>
  <p>If your order arrives damaged, please contact support within <strong>48 hours</strong> of delivery with photos of the packaging and the item.</p>

  <h2>10. Split Shipments</h2>
  <p>Orders may be shipped in multiple packages depending on supplier locations and warehouse availability. You may receive items separately.</p>

  <h2>11. Contact</h2>
  <p>Shipping support: <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
</div>
HTML;
}

private function refundPolicyHtml(string $siteName, string $supportEmail, string $today): string
{
    $siteNameEsc = e($siteName);

    return <<<HTML
<div class="prose max-w-4xl mx-auto">
  <h1>Refund Policy</h1>
  <p><strong>Last updated:</strong> {$today}</p>

  <p>This Refund Policy describes when refunds are possible on <strong>{$siteNameEsc}</strong> and how refund requests are handled.</p>

  <h2>1. Eligibility for Refunds</h2>
  <p>You may be eligible for a refund under these common cases:</p>
  <ul>
    <li>Item not delivered after reasonable shipping time (subject to carrier investigation).</li>
    <li>Item delivered damaged or defective (proof required).</li>
    <li>Wrong item received.</li>
    <li>Order cancelled before shipment (if supplier processing has not started).</li>
    <li>Product is significantly different from the description (proof required).</li>
  </ul>

  <h2>2. Non-Refundable Situations</h2>
  <p>Refunds may be denied in these situations:</p>
  <ul>
    <li>Incorrect shipping address provided by the customer.</li>
    <li>Customer refused delivery without valid reason.</li>
    <li>Normal delays caused by customs clearance, peak seasons, or carrier disruptions.</li>
    <li>Minor differences in color/packaging due to lighting or manufacturing variations.</li>
    <li>Items marked as final sale, clearance, or promotional “non-returnable” items (if clearly stated at checkout).</li>
  </ul>

  <h2>3. Refund Request Timeline</h2>
  <ul>
    <li><strong>Damaged / wrong items:</strong> request within <strong>48 hours</strong> of delivery.</li>
    <li><strong>Missing items:</strong> request within <strong>7 days</strong> of delivery.</li>
    <li><strong>Non-delivery:</strong> request after the maximum estimated delivery window has passed.</li>
  </ul>

  <h2>4. Required Evidence</h2>
  <p>To process your request quickly, we may require:</p>
  <ul>
    <li>Photos or videos of the product and packaging</li>
    <li>Order number and delivery details</li>
    <li>Tracking screenshots (if available)</li>
  </ul>

  <h2>5. Refund Methods</h2>
  <p>Approved refunds may be issued as:</p>
  <ul>
    <li>Original payment method (where supported)</li>
    <li>Store credit (faster option in many cases)</li>
    <li>Replacement shipment (where available)</li>
  </ul>

  <h2>6. Refund Processing Time</h2>
  <p>Once approved, refunds are usually processed within <strong>3–10 business days</strong>. Payment provider delays may apply depending on your bank or payment system.</p>

  <h2>7. Returns (When Applicable)</h2>
  <p>Some items may require return shipment before a refund is approved. If a return is required, we will provide instructions and the return address (if applicable).</p>
  <p>Return shipping costs may be paid by the customer unless the issue is confirmed to be our fault (wrong item or defective item).</p>

  <h2>8. Partial Refunds</h2>
  <p>In some cases, partial refunds may apply (for example: missing accessories, packaging damage, or partial delivery).</p>

  <h2>9. Chargebacks & Disputes</h2>
  <p>If you open a payment dispute or chargeback without contacting support, the refund process may take longer while the dispute is reviewed. We recommend contacting support first for faster resolution.</p>

  <h2>10. Contact</h2>
  <p>Refund requests: <a href="mailto:{$supportEmail}">{$supportEmail}</a>.</p>
</div>
HTML;
}


}

