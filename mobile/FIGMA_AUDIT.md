# Figma vs Code Audit — Summary

Generated: 2026-01-13

## Implemented screens (found in `mobile/src/screens`)
- Onboarding: HelloCardScreen, ReadyCardScreen, WelcomeScreen
- Auth: CreateAccountScreen, ForgotPasswordScreen, LoginScreen, NewPasswordScreen, PasswordCodeScreen, PasswordEntry, PasswordScreen
- Home: HomeScreen
- Shop: CategoriesFilterScreen, ShopResultsScreen
- Flash Sale: FlashSaleScreen
- Search: SearchScreen
- Products: ProductDetailScreen
- Account: AccountScreen
- Settings: SettingsScreen, PaymentMethodsScreen

## Figma screens present (exported files)
(See `mobile/figma screens` folder; full list included there.)
Key examples: Cart, Payment flows, Orders, Chat, Rewards, Profile variants, Wishlist, Recently Viewed, Many overlays/popups.

## Missing or incomplete screens / states (priority)
1. Cart
   - `Cart` main screen (empty/with-items) missing in `mobile/src/screens/cart`.
   - Cart item quantity controls, remove confirmation overlay, and checkout transition need implementation.
2. Checkout / Payment
   - Payment flow screens (Payment, Add Voucher, Payment in Progress, Failed, Success) missing in `mobile/src/screens/payment`.
   - Voucher added overlay and payment method selection screens need wiring to `payment` services.
3. Orders
   - Orders flow (To Receive, Delivery Attempt, Delivered, History, Review) missing under `mobile/src/screens/orders`.
4. Chat / Support
   - Chat screens (start, connecting, messaging variants, voucher flows) exist in `mobile/app/chat` but not mirrored in `mobile/src/screens/chat` structure. Need to standardize and wire services/state.
5. Rewards
   - Rewards screens (vouchers, progress, reminders) are in `mobile/app/rewards` but not in `mobile/src/screens/rewards`.
6. Wishlist / Recently Viewed
   - Wishlist and recently viewed screens/states are present in Figma but not implemented under `mobile/src/screens`.
7. Overlays / Modals / Toasts
   - Many Figma overlays (voucher popups, payment confirmations, settings popups) are not implemented as global overlays in `mobile/src/overlays`.
8. Settings subpages
   - Some settings screens (Edit Card, Add Card pop-up, Shipping Address edit, Preferences) missing or incomplete.
9. Misc
   - Feedback/rating screen, profile reward variants, story/product overlays, image-search recognition states need verification.

## Observations
- Project already contains a solid component library (`mobile/src/components/*`) and navigation/state foundations (`AppNavigation.tsx`, Zustand store).
- There is duplication: several screens live in `mobile/app/*` (Expo router structure) and `mobile/src/screens/*` (newer implementation). We must choose and keep consistent code paths; current work focuses on `mobile/src/screens`.
- Colors and theme tokens in `mobile/src/theme` must remain authoritative; Figma colors are only layout/source for spacing and placement.

## Action Plan (next steps)
1. Implement `Cart` screen and states under `mobile/src/screens/cart`.
2. Implement `payment` screens and voucher overlay under `mobile/src/screens/payment` and `mobile/src/overlays`.
3. Implement `orders` flow under `mobile/src/screens/orders`.
4. Move/standardize chat screens into `mobile/src/screens/chat` and wire chat state/services.
5. Implement `rewards` screens under `mobile/src/screens/rewards` and wire voucher actions.
6. Add global overlays system (`mobile/src/overlays`) to handle toasts, popups, and modal layers (state-driven, not navigation routes).
7. Run a pass to ensure every Figma state variant (loading/empty/error/success) is implemented per screen.

## Files created/updated
- `mobile/FIGMA_AUDIT.md` (this file)
- Updated todo list via task manager

## Where to start
- Prioritize: `Cart` → `Payment` → `Orders` → `Chat` → `Rewards`.
- I will implement `Cart` first and add unit/visual checks.

---

If you want me to proceed, I will implement the `Cart` screen now (create `mobile/src/screens/cart/CartScreen.tsx`, wire to `useCartStore`, and add overlays for remove confirmation).