# Store Compliance Checklist (iOS + Android)

This checklist tracks App Store / Play Store compliance risks found in the current Expo app implementation and the minimal fixes applied **without redesigning screens, changing colors, or changing layout structure**.

## Implemented fixes

### 1) Modal dialogs: accessibility + Android back handling
- **Platform:** iOS + Android
- **Risk:** Dialog overlays can trap users (Android back button not closing), and may not be announced properly by screen readers.
- **Files:**
  - `mobile/src/overlays/Dialog.tsx`
- **Fix:** Added hardware back interception to close dialogs; marked dialog container as modal for accessibility; made backdrop dismissible and labeled.
- **Test:**
  1. Open a dialog (cart delete, payment success, delete account).
  2. Press Android back → dialog closes (route does not pop).
  3. Use VoiceOver/TalkBack → dialog is announced and focus stays within dialog.

### 2) Payments: avoid misleading “card charged” copy
- **Platform:** iOS + Android
- **Risk:** Claiming a card was charged when this is a simulated flow can be misleading during review and can cause reviewers to expect real payment processing.
- **Files:**
  - `mobile/app/checkout/index.tsx`
- **Fix:** Updated success dialog copy to “Payment confirmed” wording.
- **Test:** Checkout → Pay now → Continue → success dialog message is updated.

### 3) Account deletion + sign-out availability
- **Platform:** iOS + Android
- **Risk:** If accounts exist, apps are expected to provide a clear account deletion mechanism; sign-out should be available and discoverable.
- **Files:**
  - `mobile/src/screens/settings/SettingsScreen.tsx`
  - `mobile/src/overlays/DeleteAccountDialog.tsx`
  - `mobile/src/api/account.ts`
  - `mobile/lib/authStore.tsx`
  - `mobile/lib/cartStore.tsx`
  - `mobile/lib/ordersStore.tsx`
  - `mobile/lib/paymentMethodsStore.tsx`
  - `mobile/lib/wishlistStore.tsx`
- **Fix:** Delete dialog now offers “Sign out”. Delete action attempts `/api/account/delete`, then clears local state and logs out; errors are shown via dialog.
- **Risk remaining:** Backend endpoint `/api/account/delete` must exist and actually delete accounts (or schedule deletion) server-side.
- **Test:**
  1. Settings → Delete My Account → Sign out → returns to Home and clears local state.
  2. Settings → Delete My Account → Delete Account → shows loading and then success/error dialog.

### 4) Preferences toggles: avoid fake enabled states
- **Platform:** iOS + Android
- **Risk:** Showing “Push notifications” enabled without requesting permission / implementing notifications is misleading.
- **Files:**
  - `mobile/app/settings/full.tsx`
  - `mobile/src/store/preferencesStore.tsx`
- **Fix:** Toggles are now real preferences (persisted) and default to off; enabling push shows an informational message about permissions.
- **Risk remaining:** Actual push permission prompting + registration is not implemented yet (requires a notification implementation and should be requested just-in-time).
- **Test:** Preferences → toggle switches update; enabling push shows info dialog.

### 5) API connectivity: centralized base URL + auth token support
- **Platform:** iOS + Android
- **Risk:** Hardcoded URLs and inconsistent base-url handling can cause review-time failures and unstable networking behavior.
- **Files:**
  - `mobile/src/api/config.ts`
  - `mobile/src/api/http.ts`
  - `mobile/src/api/authToken.ts`
  - `mobile/lib/api.ts`
  - `mobile/src/services/chatService.ts`
  - `mobile/.env.example`
- **Fix:** Centralized API base URLs; optional bearer token injection; storefront API uses shared client; chat uses shared base.
- **Test:** Set `EXPO_PUBLIC_API_URL` and verify storefront calls resolve; verify chat calls point at `${API}/api/chat/*`.

### 6) Privacy policy discoverability (in-app)
- **Platform:** iOS + Android
- **Risk:** Reviewers expect an accessible privacy policy reference (even if the canonical policy URL is supplied in the store listing).
- **Files:**
  - `mobile/src/screens/settings/SettingsScreen.tsx` (Terms link label)
  - `mobile/app/legal/[slug].tsx` (terms/privacy placeholder copy)
- **Fix:** Settings row now reads “Terms & Privacy” and the legal placeholder copy covers both.
- **Risk remaining:** Store listing must still provide a real Privacy Policy URL and the app should load the full policy text before launch.

### 7) Product modal close behavior + safe area handling
- **Platform:** iOS + Android
- **Risk:** Modal product routes can trap users without an obvious close affordance; notch/safe-area overlap can hide controls.
- **Files:**
  - `mobile/app/modal.tsx`
  - `mobile/src/screens/products/ProductDetailScreen.tsx`
- **Fix:** Modal view uses safe-area insets and displays a close affordance (X) with an accessibility label; close action falls back to full product or Home when deep-linked.
- **Test:**
  1. Cart → Recently viewed item → modal opens with X close button.
  2. Android back closes modal; close button also dismisses.
  3. Deep link to `/modal?slug=...` → Close navigates to product details or Home.

### 8) Recently viewed tracking + local-only storage
- **Platform:** iOS + Android
- **Risk:** “Recently viewed” should reflect actual user views and avoid implying server-side tracking when none exists.
- **Files:**
  - `mobile/src/screens/products/ProductDetailScreen.tsx`
  - `mobile/lib/recentlyViewedStore.tsx`
- **Fix:** Tracking fires once after product detail data loads for all entry points; storage is in-memory only (no network or PII).
- **Test:** Open product from home/search/cart modal → item appears in cart’s “Recently viewed” list.

## Known gaps (must be handled before store submission)

### A) Permissions (camera/photos/notifications)
- **Platform:** iOS + Android
- **Risk:** When camera/photos/notifications are implemented, permissions must be requested just-in-time with clear rationale and graceful denied handling.
- **Current status:** Image search screens are currently UI-only and do not access camera/photos.
- **Required before submission:** Add a permission flow (and update `Info.plist`/Android permission strings) when implementing the actual feature.

### B) Account deletion endpoint
- **Platform:** iOS + Android
- **Risk:** Client-side “delete” without server deletion is insufficient once real accounts exist.
- **Required before submission:** Implement `/api/account/delete` with authenticated deletion and return clear success/error.
