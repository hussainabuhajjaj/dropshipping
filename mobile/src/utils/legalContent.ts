/**
 * Legal content for Privacy Policy and Terms of Service
 * These are simplified versions for in-app display.
 * Full versions are available in assets/legal/ directory.
 */

export const legalContent: Record<string, { title: string; content: string }> = {
  privacy: {
    title: 'Privacy Policy',
    content: `Last Updated: February 28, 2026

INTRODUCTION
Simbazu is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and safeguard your information.

INFORMATION WE COLLECT
• Personal information (name, email, phone, address)
• Payment information (processed securely by payment providers)
• Device and usage data
• Order and transaction history

HOW WE USE YOUR INFORMATION
• Process and fulfill orders
• Communicate about orders and account
• Provide customer support
• Send marketing communications (with consent)
• Improve our Service
• Detect and prevent fraud

INFORMATION SHARING
We share information with:
• Payment processors (Korapay, Paystack)
• Shipping providers (CJ Dropshipping)
• Analytics and support tools
• As required by law

YOUR RIGHTS
• Access your personal information
• Update or correct your information
• Delete your account and data
• Opt-out of marketing
• Export your data

ACCOUNT DELETION
You can delete your account through app settings. When deleted:
• Personal information is permanently removed
• Order history is anonymized
• You're logged out from all devices
• This action cannot be undone

DATA SECURITY
We protect your information using:
• Encryption in transit and at rest
• Secure authentication
• Regular security audits
• Access controls

CONTACT US
Email: info@simbazu.net
Support: Available through app chat

By using our Service, you consent to this privacy policy.`,
  },

  terms: {
    title: 'Terms of Service',
    content: `Last Updated: February 28, 2026

AGREEMENT TO TERMS
By using Simbazu, you agree to these Terms of Service. If you don't agree, please don't use the Service.

DESCRIPTION OF SERVICE
Simbazu is an e-commerce platform connecting customers with products through dropshipping partnerships.

ELIGIBILITY
You must be at least 13 years old to use our Service.

ACCOUNT REGISTRATION
• Provide valid email and secure password
• Keep your credentials confidential
• You're responsible for account activity
• Delete your account anytime in settings

ORDERS AND PAYMENTS
• Prices may change without notice
• Payment processed securely via Korapay/Paystack
• Order confirmation doesn't guarantee availability
• We reserve the right to accept or reject orders

SHIPPING AND DELIVERY
• We ship to select countries worldwide
• Delivery times are estimates
• You're responsible for customs fees and import duties
• We're not liable for carrier delays

RETURNS AND REFUNDS
• Returns accepted within 30 days
• Products must be unused in original packaging
• Refunds processed within 5-10 business days
• Report damaged items within 7 days

PROHIBITED USES
You agree not to:
• Use the Service for illegal purposes
• Violate laws or regulations
• Infringe intellectual property rights
• Harass or harm other users
• Use automated systems without authorization

INTELLECTUAL PROPERTY
All content is owned by Simbazu or licensors. By submitting reviews or content, you grant us license to use it.

DISCLAIMERS
Service provided "as is" without warranties. We don't guarantee uninterrupted or error-free service.

LIMITATION OF LIABILITY
Our liability is limited to the amount you paid for products. We're not liable for indirect or consequential damages.

PRIVACY
Your use is governed by our Privacy Policy. Please review it to understand our data practices.

MODIFICATIONS
We may modify the Service or these Terms at any time. Continued use constitutes acceptance.

CONTACT US
Email: info@simbazu.net
Support: Available through app chat

By using the Service, you agree to these Terms.`,
  },

  shipping: {
    title: 'Shipping Policy',
    content: `Last Updated: February 28, 2026

SHIPPING LOCATIONS
We ship to select countries worldwide. Available locations are shown at checkout.

DELIVERY TIMES
• Estimated delivery: 7-21 business days
• Times vary by location and product
• Customs clearance may cause delays
• Tracking information provided after shipment

SHIPPING COSTS
• Calculated at checkout based on destination
• Free shipping on orders over specified amount
• Express shipping available for select locations

CUSTOMS AND DUTIES
• You're responsible for customs fees and import duties
• These are not included in product or shipping costs
• Contact your local customs office for rates

ORDER TRACKING
• Tracking number sent via email after shipment
• Track orders in the app under "Orders"
• Updates may take 24-48 hours to appear

DELIVERY ISSUES
• Contact us if order doesn't arrive within estimated time
• We'll work with carrier to locate package
• Refund or replacement provided if lost

Contact: info@simbazu.net`,
  },

  refund: {
    title: 'Refund Policy',
    content: `Last Updated: February 28, 2026

RETURN ELIGIBILITY
• Returns accepted within 30 days of delivery
• Products must be unused and in original packaging
• Original receipt or proof of purchase required
• Some items may not be eligible for return

REFUND PROCESS
1. Submit refund request through app
2. We review within 48 hours
3. If approved, return shipping instructions provided
4. Refund processed within 5-10 business days after receipt

REFUND METHOD
• Refunds issued to original payment method
• Processing time varies by payment provider
• You'll receive email confirmation

NON-REFUNDABLE ITEMS
• Personalized or custom products
• Digital products after download
• Intimate or sanitary goods
• Perishable items

DAMAGED OR DEFECTIVE PRODUCTS
• Report within 7 days of delivery
• Provide photos and description
• Replacement or full refund provided
• Return shipping covered by us

PARTIAL REFUNDS
May be granted for:
• Items with obvious signs of use
• Items not in original condition
• Items missing parts not due to our error

LATE OR MISSING REFUNDS
If you haven't received refund:
1. Check your bank account
2. Contact your payment provider
3. Contact us at info@simbazu.net

EXCHANGES
We only replace defective or damaged items. Contact us for exchange process.

Contact: info@simbazu.net`,
  },
};

export function getLegalContent(slug: string): { title: string; content: string } | null {
  return legalContent[slug] || null;
}
