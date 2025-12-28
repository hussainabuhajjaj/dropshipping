## CJ Launch Plan

1. **Stabilize CJ pages**
   - Finish the CJ “My Products” table (image modal, delivery, warehouses) and make sure required migrations (`c_j_webhook_logs`) exist.
   - Wire the CJ client into sync/backoff-aware jobs and expose status metadata in the Filament page.
2. **Automate CJ catalog sync**
   - Create a console command (`cj:sync-catalog`) that pulls categories/products, respects rate limits, and stores summaries in `site_settings`.
   - Add a scheduled job that can be triggered manually (via Filament action) to refresh the catalog.
3. **Seed & test data**
   - Expand `FullTestDataSeeder`/`CJSeeder` to include suppliers, CJ categories, variants, warehouses, andCJ inventory snapshots.
   - Ensure payment/refund flows use seeded records and add domain-to-eloquent adapters.
4. **Storefront UX polish**
   - Build the Noon-inspired homepage (hero, offer rails, category cards), wire wishlist/cart features, and surface product details (reviews, specs).
   - Strengthen account flows (separate guards, profile links, timeline) and consistent header dropdowns.
5. **Admin notifications & CJ UI**
   - Fix Filament option issues (`makeForm`, `navigationGroup` types) and add CJ webhook log resource/table.
   - Build CJ sourcing and webhook monitoring pages with actions to create sourcing requests.
6. **Testing & documentation**
   - Implement tests for wishlist controller, CJ client endpoints, checkout stock guard, and the new sync command.
   - Update docs/OperationsBooklet with usage guides, CJ integration steps, and seeding instructions.

### Catalog Filters and Sort

- Warehouse filter: sent as `warehouseId` to `/v1/product/list` and `/v1/product/listV2`.
- In-stock-only filter: sent as `haveStock=1` when enabled.
- Sort codes: mapped to `sort` and validated.
   - `1`: Price low → high
   - `2`: Price high → low
   - `5`: Newest
   - `6`: Best selling
- Default sort: omitted (blank) to allow CJ’s default "best match" when supported.

References:
- Product list: https://developers.cjdropshipping.com/api2.0/v1/product/list
- Product list V2: https://developers.cjdropshipping.com/api2.0/v1/product/listV2

Notes:
- If CJ expects different parameter names (e.g., `sortType`, `storageStatus`), update `CjProductApi::listProducts()` and `CJCatalog::fetch()` accordingly.
- Warehouse list endpoint: `/v1/product/globalWarehouse/list` is used to populate filter options; failures surface a warning and disable selection.

### Work in progress
