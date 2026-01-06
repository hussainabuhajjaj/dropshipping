<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Models\StorefrontSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::updateOrCreate([], [
            'site_name' => 'Dispatch Store',
            'site_description' => 'Curated essentials delivered fast.',
            'meta_title' => 'Dispatch Store',
            'meta_description' => 'Shop curated essentials with reliable shipping and easy returns.',
            'meta_keywords' => 'dropshipping, store, ecommerce, deals',
            'logo_path' => null,
            'favicon_path' => null,
            'timezone' => config('app.timezone', 'UTC'),
            'primary_color' => '#0f172a',
            'secondary_color' => '#f97316',
            'accent_color' => '#22c55e',
            'support_email' => 'support@dispatch.store',
            'support_whatsapp' => '+22500000000',
            'support_phone' => '+22500000000',
            'support_hours' => 'Mon-Sat, 9:00-18:00 GMT',
            'delivery_window' => '7-18 business days',
            'shipping_message' => 'Standard tracked delivery to Cote dIvoire.',
            'customs_message' => 'Duties and VAT are disclosed before payment when available.',
            'tax_label' => 'VAT',
            'tax_rate' => 0,
            'tax_included' => false,
            'shipping_handling_fee' => 0,
            'free_shipping_threshold' => null,
            'auto_approve_reviews' => false,
            'auto_approve_review_days' => 0,
            
            // About Page
            'about_page_html' => '<div class="prose max-w-4xl mx-auto">
                <h1>About Dispatch Store</h1>
                <p class="lead">We\'re redefining online shopping with transparency, reliability, and customer-first service.</p>
                
                <h2>Our Story</h2>
                <p>Founded in 2024, Dispatch Store was born from a simple idea: online shopping should be transparent, reliable, and accessible to everyone. We noticed that too many shoppers were frustrated with hidden fees, unclear shipping times, and poor customer service. We set out to change that.</p>
                
                <h2>What Makes Us Different</h2>
                <ul>
                    <li><strong>Complete Transparency:</strong> All costs, including customs duties and taxes, are clearly shown before you complete your purchase. No surprises at delivery.</li>
                    <li><strong>Reliable Shipping:</strong> We partner with trusted carriers to ensure your orders arrive safely within 7-18 business days.</li>
                    <li><strong>Quality Assurance:</strong> Every product is carefully selected and verified before being listed in our store.</li>
                    <li><strong>Outstanding Support:</strong> Our customer service team is available Mon-Sat to help with any questions or concerns.</li>
                </ul>
                
                <h2>Our Commitment</h2>
                <p>We\'re committed to providing:</p>
                <ul>
                    <li>Fair and competitive pricing</li>
                    <li>Accurate product descriptions and images</li>
                    <li>Real-time order tracking</li>
                    <li>Hassle-free returns and refunds</li>
                    <li>Secure payment processing</li>
                    <li>Responsive customer support</li>
                </ul>
                
                <h2>Contact Us</h2>
                <p>Have questions? We\'re here to help!</p>
                <ul>
                    <li>Email: support@dispatch.store</li>
                    <li>WhatsApp: +22500000000</li>
                    <li>Phone: +22500000000</li>
                    <li>Hours: Mon-Sat, 9:00-18:00 GMT</li>
                </ul>
            </div>',
            
            // Shipping Policy
            'shipping_policy' => '<div class="prose max-w-4xl mx-auto">
                <h1>Shipping Policy</h1>
                <p class="lead">Last updated: December 27, 2025</p>
                
                <h2>Processing Time</h2>
                <p>Orders are typically processed within 1-3 business days after payment confirmation. You will receive an email notification once your order has been shipped with tracking information.</p>
                
                <h2>Delivery Times</h2>
                <p>Standard delivery to Côte d\'Ivoire takes approximately <strong>7-18 business days</strong> from the date of shipment. Delivery times may vary depending on:</p>
                <ul>
                    <li>Product availability and location</li>
                    <li>Customs clearance procedures</li>
                    <li>Local courier efficiency</li>
                    <li>Weather and force majeure events</li>
                </ul>
                
                <h2>Shipping Costs</h2>
                <p>Shipping costs are calculated based on the total weight and destination of your order. The exact shipping fee will be displayed at checkout before you complete your purchase.</p>
                <p><strong>Free Shipping:</strong> We offer free shipping on orders over a certain threshold. Check our homepage or promotional banners for current offers.</p>
                
                <h2>Tracking Your Order</h2>
                <p>Once your order ships, you\'ll receive a tracking number via email. You can track your package at any time by:</p>
                <ul>
                    <li>Visiting our <a href="/orders/track">Track Order</a> page</li>
                    <li>Logging into your account and viewing your order history</li>
                    <li>Using the tracking number on the carrier\'s website</li>
                </ul>
                
                <h2>Customs and Import Duties</h2>
                <p>All applicable customs duties, taxes, and fees are calculated and displayed <strong>before checkout</strong>. The price you see at checkout is the final price you\'ll pay—no hidden fees or surprises at delivery.</p>
                
                <h2>Delivery Issues</h2>
                <p>If you experience any issues with your delivery, including:</p>
                <ul>
                    <li>Non-delivery after expected timeframe</li>
                    <li>Package damage</li>
                    <li>Missing items</li>
                </ul>
                <p>Please contact our support team immediately at <a href="mailto:support@dispatch.store">support@dispatch.store</a> or via WhatsApp at +22500000000.</p>
                
                <h2>International Shipping</h2>
                <p>Currently, we primarily serve customers in Côte d\'Ivoire. For shipping to other locations, please contact our support team to discuss availability and costs.</p>
                
                <h2>Address Changes</h2>
                <p>If you need to change your shipping address, please contact us <strong>within 24 hours</strong> of placing your order. Once the order has shipped, we cannot modify the delivery address.</p>
                
                <h2>Questions?</h2>
                <p>For any shipping-related questions, contact us at <a href="mailto:support@dispatch.store">support@dispatch.store</a></p>
            </div>',
            
            // Refund Policy
            'refund_policy' => '<div class="prose max-w-4xl mx-auto">
                <h1>Refund & Return Policy</h1>
                <p class="lead">Last updated: December 27, 2025</p>
                
                <h2>Our Guarantee</h2>
                <p>We stand behind the quality of our products. If you\'re not completely satisfied with your purchase, we\'re here to help with returns and refunds.</p>
                
                <h2>Return Eligibility</h2>
                <p>You may return most items within <strong>30 days</strong> of delivery for a full refund if they meet the following conditions:</p>
                <ul>
                    <li>Item is in original, unused condition</li>
                    <li>Original packaging and tags are intact</li>
                    <li>Item is not on the non-returnable list (see below)</li>
                    <li>Proof of purchase is provided</li>
                </ul>
                
                <h2>Non-Returnable Items</h2>
                <p>Certain items cannot be returned for hygiene and safety reasons:</p>
                <ul>
                    <li>Personal care items (cosmetics, skincare, etc.)</li>
                    <li>Intimate apparel and swimwear</li>
                    <li>Perishable goods</li>
                    <li>Digital products and gift cards</li>
                    <li>Custom or personalized items</li>
                </ul>
                
                <h2>How to Initiate a Return</h2>
                <ol>
                    <li><strong>Contact Us:</strong> Email <a href="mailto:support@dispatch.store">support@dispatch.store</a> with your order number and reason for return</li>
                    <li><strong>Receive Authorization:</strong> We\'ll provide a return authorization and instructions</li>
                    <li><strong>Package Item:</strong> Securely pack the item in original packaging</li>
                    <li><strong>Ship Return:</strong> Send to the address provided in your authorization</li>
                </ol>
                
                <h2>Refund Processing</h2>
                <p>Once we receive and inspect your return:</p>
                <ul>
                    <li>Approved returns are processed within <strong>3-5 business days</strong></li>
                    <li>Refunds are issued to your original payment method</li>
                    <li>It may take 5-10 business days for the refund to appear in your account</li>
                    <li>You\'ll receive email confirmation once the refund is processed</li>
                </ul>
                
                <h2>Return Shipping Costs</h2>
                <p><strong>Defective or Incorrect Items:</strong> We cover all return shipping costs</p>
                <p><strong>Change of Mind:</strong> Customer is responsible for return shipping unless item arrives damaged or defective</p>
                
                <h2>Exchanges</h2>
                <p>Currently, we don\'t offer direct exchanges. If you need a different size or color:</p>
                <ol>
                    <li>Return the original item for a refund</li>
                    <li>Place a new order for the item you want</li>
                </ol>
                
                <h2>Damaged or Defective Items</h2>
                <p>If you receive a damaged or defective item:</p>
                <ul>
                    <li>Contact us within <strong>48 hours</strong> of delivery</li>
                    <li>Provide photos of the damage/defect</li>
                    <li>We\'ll arrange a replacement or full refund</li>
                    <li>No return shipping required for defective items</li>
                </ul>
                
                <h2>Wrong Item Received</h2>
                <p>If you receive the wrong item, contact us immediately. We\'ll send the correct item at no additional cost and provide a prepaid return label for the incorrect item.</p>
                
                <h2>Partial Refunds</h2>
                <p>In some cases, partial refunds may be granted for:</p>
                <ul>
                    <li>Items showing obvious signs of use</li>
                    <li>Items not in original condition or missing parts</li>
                    <li>Items returned more than 30 days after delivery</li>
                </ul>
                
                <h2>Questions?</h2>
                <p>Contact our support team at <a href="mailto:support@dispatch.store">support@dispatch.store</a> or WhatsApp +22500000000</p>
            </div>',
            
            // Privacy Policy
            'privacy_policy' => '<div class="prose max-w-4xl mx-auto">
                <h1>Privacy Policy</h1>
                <p class="lead">Last updated: December 27, 2025</p>
                
                <h2>Introduction</h2>
                <p>Dispatch Store ("we", "our", or "us") respects your privacy and is committed to protecting your personal data. This privacy policy explains how we collect, use, and safeguard your information when you use our website and services.</p>
                
                <h2>Information We Collect</h2>
                
                <h3>Personal Information</h3>
                <p>When you create an account or place an order, we collect:</p>
                <ul>
                    <li>Name and contact information (email, phone number)</li>
                    <li>Shipping and billing addresses</li>
                    <li>Payment information (processed securely through our payment provider)</li>
                    <li>Order history and preferences</li>
                </ul>
                
                <h3>Automatically Collected Information</h3>
                <p>When you visit our website, we automatically collect:</p>
                <ul>
                    <li>IP address and device information</li>
                    <li>Browser type and version</li>
                    <li>Pages visited and time spent on site</li>
                    <li>Referral source</li>
                    <li>Cookies and similar tracking technologies</li>
                </ul>
                
                <h2>How We Use Your Information</h2>
                <p>We use your information to:</p>
                <ul>
                    <li>Process and fulfill your orders</li>
                    <li>Communicate about orders, shipping, and customer service</li>
                    <li>Improve our website and services</li>
                    <li>Send promotional emails (with your consent)</li>
                    <li>Prevent fraud and enhance security</li>
                    <li>Comply with legal obligations</li>
                    <li>Analyze website usage and trends</li>
                </ul>
                
                <h2>Data Sharing and Disclosure</h2>
                <p>We do not sell your personal information. We may share your data with:</p>
                <ul>
                    <li><strong>Service Providers:</strong> Shipping carriers, payment processors, email service providers</li>
                    <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
                    <li><strong>Business Transfers:</strong> In the event of a merger, acquisition, or sale</li>
                </ul>
                
                <h2>Cookies and Tracking</h2>
                <p>We use cookies to:</p>
                <ul>
                    <li>Remember your preferences and login status</li>
                    <li>Analyze website traffic and usage patterns</li>
                    <li>Personalize your shopping experience</li>
                    <li>Serve relevant advertisements</li>
                </ul>
                <p>You can control cookies through your browser settings. However, disabling cookies may affect website functionality.</p>
                
                <h2>Data Security</h2>
                <p>We implement industry-standard security measures to protect your information:</p>
                <ul>
                    <li>SSL encryption for data transmission</li>
                    <li>Secure servers and databases</li>
                    <li>Regular security audits</li>
                    <li>Limited employee access to personal data</li>
                    <li>PCI DSS compliance for payment processing</li>
                </ul>
                
                <h2>Your Rights</h2>
                <p>You have the right to:</p>
                <ul>
                    <li><strong>Access:</strong> Request a copy of your personal data</li>
                    <li><strong>Correction:</strong> Update or correct inaccurate information</li>
                    <li><strong>Deletion:</strong> Request deletion of your account and data</li>
                    <li><strong>Opt-Out:</strong> Unsubscribe from marketing emails</li>
                    <li><strong>Data Portability:</strong> Receive your data in a portable format</li>
                </ul>
                <p>To exercise these rights, contact us at <a href="mailto:privacy@dispatch.store">privacy@dispatch.store</a></p>
                
                <h2>Data Retention</h2>
                <p>We retain your information for as long as necessary to:</p>
                <ul>
                    <li>Provide our services</li>
                    <li>Comply with legal obligations</li>
                    <li>Resolve disputes</li>
                    <li>Enforce our agreements</li>
                </ul>
                
                <h2>Children\'s Privacy</h2>
                <p>Our services are not intended for children under 13 years old. We do not knowingly collect personal information from children. If you believe we have inadvertently collected such information, please contact us immediately.</p>
                
                <h2>Third-Party Links</h2>
                <p>Our website may contain links to third-party websites. We are not responsible for the privacy practices of these external sites. Please review their privacy policies before providing any information.</p>
                
                <h2>International Data Transfers</h2>
                <p>Your information may be transferred to and processed in countries outside of your residence. We ensure appropriate safeguards are in place to protect your data in accordance with this privacy policy.</p>
                
                <h2>Changes to This Policy</h2>
                <p>We may update this privacy policy periodically. We will notify you of significant changes by:</p>
                <ul>
                    <li>Posting the updated policy on our website</li>
                    <li>Updating the "Last updated" date</li>
                    <li>Sending email notifications for material changes</li>
                </ul>
                
                <h2>Contact Us</h2>
                <p>For privacy-related questions or concerns:</p>
                <ul>
                    <li>Email: <a href="mailto:privacy@dispatch.store">privacy@dispatch.store</a></li>
                    <li>Phone: +22500000000</li>
                    <li>Address: [Your Physical Address]</li>
                </ul>
            </div>',
            
            // Terms of Service
            'terms_of_service' => '<div class="prose max-w-4xl mx-auto">
                <h1>Terms of Service</h1>
                <p class="lead">Last updated: December 27, 2025</p>
                
                <h2>1. Acceptance of Terms</h2>
                <p>By accessing and using Dispatch Store ("Website", "Service"), you accept and agree to be bound by these Terms of Service ("Terms"). If you do not agree to these Terms, please do not use our Service.</p>
                
                <h2>2. Eligibility</h2>
                <p>You must be at least 18 years old to use our Service. By using our Service, you represent and warrant that you meet this age requirement and have the legal capacity to enter into these Terms.</p>
                
                <h2>3. Account Registration</h2>
                <p>To place orders, you must create an account. You agree to:</p>
                <ul>
                    <li>Provide accurate, current, and complete information</li>
                    <li>Maintain and update your account information</li>
                    <li>Keep your password confidential</li>
                    <li>Notify us immediately of any unauthorized access</li>
                    <li>Accept responsibility for all activities under your account</li>
                </ul>
                
                <h2>4. Orders and Payment</h2>
                
                <h3>4.1 Placing Orders</h3>
                <p>When you place an order, you are making an offer to purchase products. We reserve the right to accept or decline your order for any reason.</p>
                
                <h3>4.2 Pricing</h3>
                <ul>
                    <li>All prices are listed in the displayed currency</li>
                    <li>Prices include applicable taxes and duties (shown at checkout)</li>
                    <li>We reserve the right to change prices without notice</li>
                    <li>Pricing errors will be corrected, and you\'ll have the option to cancel</li>
                </ul>
                
                <h3>4.3 Payment</h3>
                <ul>
                    <li>Payment is required at the time of order</li>
                    <li>We accept major credit cards and other listed payment methods</li>
                    <li>Payment processing is handled by secure third-party providers</li>
                    <li>Your payment information is encrypted and not stored on our servers</li>
                </ul>
                
                <h2>5. Shipping and Delivery</h2>
                <p>Shipping terms are detailed in our <a href="/legal/shipping-policy">Shipping Policy</a>. Key points:</p>
                <ul>
                    <li>Delivery times are estimates, not guarantees</li>
                    <li>Risk of loss transfers upon delivery</li>
                    <li>You are responsible for providing accurate delivery information</li>
                </ul>
                
                <h2>6. Returns and Refunds</h2>
                <p>Our return and refund procedures are outlined in our <a href="/legal/refund-policy">Refund Policy</a>. Returns must comply with stated conditions and timeframes.</p>
                
                <h2>7. Product Information</h2>
                <ul>
                    <li>We strive for accurate product descriptions and images</li>
                    <li>Colors and sizes may vary slightly from images</li>
                    <li>We do not warrant that product descriptions are error-free</li>
                    <li>We reserve the right to correct errors or update information</li>
                </ul>
                
                <h2>8. Intellectual Property</h2>
                <p>All content on this Website, including but not limited to text, graphics, logos, images, and software, is our property or our licensors\' property and is protected by copyright, trademark, and other intellectual property laws.</p>
                <p>You may not:</p>
                <ul>
                    <li>Copy, reproduce, or distribute our content without permission</li>
                    <li>Modify or create derivative works</li>
                    <li>Use our content for commercial purposes</li>
                    <li>Remove copyright or proprietary notices</li>
                </ul>
                
                <h2>9. Prohibited Activities</h2>
                <p>You agree not to:</p>
                <ul>
                    <li>Violate any laws or regulations</li>
                    <li>Infringe on intellectual property rights</li>
                    <li>Transmit harmful code or viruses</li>
                    <li>Attempt to gain unauthorized access</li>
                    <li>Engage in fraudulent activities</li>
                    <li>Harass or harm other users</li>
                    <li>Use automated systems (bots, scrapers) without permission</li>
                    <li>Interfere with Website functionality</li>
                </ul>
                
                <h2>10. User Content</h2>
                <p>If you submit reviews, comments, or other content:</p>
                <ul>
                    <li>You grant us a non-exclusive, worldwide license to use such content</li>
                    <li>You represent that you own or have rights to the content</li>
                    <li>We may remove content that violates these Terms</li>
                    <li>We are not responsible for user-generated content</li>
                </ul>
                
                <h2>11. Disclaimers</h2>
                <p><strong>THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND.</strong></p>
                <p>We disclaim all warranties, express or implied, including:</p>
                <ul>
                    <li>Merchantability</li>
                    <li>Fitness for a particular purpose</li>
                    <li>Non-infringement</li>
                    <li>Uninterrupted or error-free operation</li>
                </ul>
                
                <h2>12. Limitation of Liability</h2>
                <p>TO THE MAXIMUM EXTENT PERMITTED BY LAW:</p>
                <ul>
                    <li>We are not liable for indirect, incidental, or consequential damages</li>
                    <li>Our total liability shall not exceed the amount you paid for the product</li>
                    <li>We are not liable for delays or failures due to circumstances beyond our control</li>
                </ul>
                
                <h2>13. Indemnification</h2>
                <p>You agree to indemnify and hold us harmless from any claims, damages, or expenses arising from:</p>
                <ul>
                    <li>Your violation of these Terms</li>
                    <li>Your violation of any rights of others</li>
                    <li>Your use of the Service</li>
                </ul>
                
                <h2>14. Termination</h2>
                <p>We may terminate or suspend your account and access to the Service:</p>
                <ul>
                    <li>For violation of these Terms</li>
                    <li>For fraudulent or illegal activity</li>
                    <li>At our sole discretion without notice</li>
                </ul>
                <p>Upon termination, your right to use the Service ceases immediately.</p>
                
                <h2>15. Governing Law</h2>
                <p>These Terms are governed by the laws of [Your Jurisdiction], without regard to conflict of law principles. Any disputes shall be resolved in the courts of [Your Jurisdiction].</p>
                
                <h2>16. Dispute Resolution</h2>
                <p>For any disputes:</p>
                <ol>
                    <li>Contact us first to seek informal resolution</li>
                    <li>If unresolved, disputes may be subject to binding arbitration</li>
                    <li>Class action waivers may apply where permitted</li>
                </ol>
                
                <h2>17. Changes to Terms</h2>
                <p>We reserve the right to modify these Terms at any time. Changes become effective upon posting. Your continued use of the Service after changes constitutes acceptance of the modified Terms.</p>
                
                <h2>18. Severability</h2>
                <p>If any provision of these Terms is found invalid or unenforceable, the remaining provisions shall remain in full effect.</p>
                
                <h2>19. Entire Agreement</h2>
                <p>These Terms, along with our Privacy Policy and other referenced policies, constitute the entire agreement between you and Dispatch Store.</p>
                
                <h2>20. Contact Information</h2>
                <p>For questions about these Terms:</p>
                <ul>
                    <li>Email: <a href="mailto:legal@dispatch.store">legal@dispatch.store</a></li>
                    <li>Phone: +22500000000</li>
                    <li>Address: [Your Physical Address]</li>
                </ul>
            </div>',
        ]);

        StorefrontSetting::updateOrCreate([], [
            'brand_name' => 'Simbazu',
            'footer_blurb' => 'Global sourcing with local clarity. Track every step and see customs details before you pay.',
            'delivery_notice' => "Delivery to Cote d'Ivoire with duties shown before checkout.",
            'copyright_text' => 'Simbazu',
            'header_links' => [
                ['label' => 'Shop', 'href' => '/products'],
                ['label' => 'Track order', 'href' => '/orders/track'],
                ['label' => 'Support', 'href' => '/support'],
                ['label' => 'FAQ', 'href' => '/faq'],
            ],
            'footer_columns' => [
                [
                    'title' => 'Shop',
                    'links' => [
                        ['label' => 'All products', 'href' => '/products'],
                        ['label' => 'Track order', 'href' => '/orders/track'],
                        ['label' => 'Cart', 'href' => '/cart'],
                        ['label' => 'Checkout', 'href' => '/checkout'],
                    ],
                ],
                [
                    'title' => 'Support',
                    'links' => [
                        ['label' => 'Contact', 'href' => '/support'],
                        ['label' => 'FAQ', 'href' => '/faq'],
                        ['label' => 'About', 'href' => '/about'],
                        ['label' => 'My orders', 'href' => '/orders'],
                    ],
                ],
                [
                    'title' => 'Account',
                    'links' => [
                        ['label' => 'Overview', 'href' => '/account'],
                        ['label' => 'Notifications', 'href' => '/account/notifications'],
                        ['label' => 'Orders', 'href' => '/orders'],
                        ['label' => 'Addresses', 'href' => '/account/addresses'],
                        ['label' => 'Payment methods', 'href' => '/account/payments'],
                        ['label' => 'Refunds', 'href' => '/account/refunds'],
                        ['label' => 'Wallet', 'href' => '/account/wallet'],
                    ],
                ],
                [
                    'title' => 'Legal',
                    'links' => [
                        ['label' => 'Shipping policy', 'href' => '/legal/shipping-policy'],
                        ['label' => 'Refund policy', 'href' => '/legal/refund-policy'],
                        ['label' => 'Terms of service', 'href' => '/legal/terms-of-service'],
                        ['label' => 'Privacy policy', 'href' => '/legal/privacy-policy'],
                    ],
                ],
            ],
            'value_props' => [
                [
                    'title' => "Delivery built for Cote d'Ivoire",
                    'body' => 'Standard delivery in 7 to 18 business days with proactive tracking updates.',
                ],
                [
                    'title' => 'Smart sourcing, safer spending',
                    'body' => 'We verify supplier availability, quality, and customs requirements before checkout.',
                ],
                [
                    'title' => 'Support that responds',
                    'body' => 'Get answers fast via WhatsApp and email with order-ready agents.',
                ],
            ],
            'social_links' => [],
        ]);
    }
}
