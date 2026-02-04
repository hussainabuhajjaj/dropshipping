# Mobile Storefront (Expo)

This directory contains the React Native + Expo app that mirrors the storefront experience.

## Screen map

Tabs:
- Home: deals, promo carousel, categories, trending products
- Categories: category grid
- Search: search + results
- Cart: cart summary and checkout CTA
- Account: profile hub + shortcuts

Stacks:
- Products list: /products?category=...&q=...
- Product detail: /products/[slug]
- Checkout: /checkout
- Orders: /orders, /orders/[number], /orders/track
- Auth: /auth/login, /auth/register, /auth/forgot
- Account subpages: /account/wishlist, /account/notifications, /account/addresses, /account/payments, /account/refunds, /account/wallet
- Support: /support, /faq, /about, /contact
- Legal: /legal/[slug]

## Data wiring

Storefront screens call the Laravel API via `lib/storefront.ts`, with mock data fallbacks from
`lib/mockData.ts` and `lib/mockOrders.ts` whenever the API is unavailable. Cart state and coupons
remain local-only for now.

Set `EXPO_PUBLIC_API_URL` (or `EXPO_PUBLIC_API_BASE_URL`) to point at your Laravel host (defaults to `http://dropshipping.test`).
Example:

```
EXPO_PUBLIC_API_URL=http://dropshipping.test
```

Support chat uses `src/services/chatService.ts` and expects backend endpoints under `/api/chat/*`. If your Laravel app does not expose these yet, point `BACKEND_URL` / `EXPO_PUBLIC_API_URL` at the service that does.

## Run

```
cd mobile
npm install
npm run start
```
