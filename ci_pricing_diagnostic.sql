-- =====================================================================
-- C1.2 READ-ONLY DIAGNOSTIC — CI PRICING (XOF) ONLY
-- SELECT statements only. No UPDATE/INSERT/DELETE/ALTER/DROP/CREATE.
-- Run against the SAME staging DB used for pricing:ci-diff-report.
-- MySQL 8+ (JSON functions). Safe to run repeatedly.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Q0. Independent baseline (must be identical before/after this script)
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS product_count,
       MAX(updated_at) AS max_updated_at,
       ROUND(SUM(selling_price), 2) AS selling_price_sum
FROM products;

-- ---------------------------------------------------------------------
-- Q1. THE 13.04% PRODUCTS — full persisted inputs & intermediates
--     Key question: does pricing_meta.category_multiplier exist and is
--     it ~1.1304 while current config-derived multiplier differs?
-- ---------------------------------------------------------------------
SELECT
    p.id                                   AS product_id,
    p.code                                 AS sku,
    p.cost_price,
    p.currency,
    p.selling_price                        AS stored_price,
    p.category_id,
    p.local_warehouse_id                   AS warehouse_id,
    lw.name                                AS warehouse_name,
    lw.shipping_cost_per_kg                AS rate_per_kg,
    lw.shipping_base_cost                  AS base_cost,
    lw.shipping_additional_cost            AS additional_cost,
    lw.shipping_min_charge                 AS min_charge,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg'))            AS meta_weight_kg,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping'))          AS meta_cj_shipping,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping'))    AS meta_external_shipping,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.landed_cost'))          AS meta_landed_cost,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used'))          AS meta_margin_used,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_id'))          AS meta_category_id,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier'))  AS meta_category_multiplier,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.shipping_rate_per_kg')) AS meta_rate_per_kg,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.xof_buffer_percent'))   AS meta_xof_buffer,
    -- stored vs landed x (1+margin) ratio: isolates ALL multiplicative factors
    ROUND(p.selling_price / NULLIF(JSON_EXTRACT(p.pricing_meta,'$.landed_cost'),0), 6) AS stored_over_landed,
    -- if margin persisted, residual factor = stored / (landed*(1+margin))
    ROUND(p.selling_price / NULLIF(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4))),0), 6) AS residual_factor_after_margin,
    p.source_url,
    p.created_at,
    p.updated_at
FROM products p
LEFT JOIN local_ware_houses lw ON lw.id = p.local_warehouse_id
WHERE p.id IN (28,1247,42,1246,33,17,51,73,1248,1256)
ORDER BY p.id;

-- ---------------------------------------------------------------------
-- Q1b. Residual-factor histogram across ALL XOF products with meta.
--      If a cluster sits at ~1.1304, that IS the historical factor.
--      Compare each cluster value against CURRENT config multipliers
--      (env-overridable: PRICING_*_MULTIPLIER defaults 1.2/1.1/1.15/1.25/1.1/1.0)
--      NOTE: margin_used is persisted as a FRACTION (PricingService.php:178
--      stores resolveMarginByWeight() return; Filament displays it x100).
--      Weight margins today: 0.65/0.55/0.45/0.30(default 0.35).
-- ---------------------------------------------------------------------
SELECT factor_bucket, COUNT(*) n,
       MIN(residual) min_residual, MAX(residual) max_residual,
       MIN(id) example_low_id, MAX(id) example_high_id
FROM (
  SELECT p.id,
    p.selling_price / NULLIF(CAST(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') AS DECIMAL(12,4))
          * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4))),0) AS residual,
    CASE
      WHEN ABS(p.selling_price / NULLIF(CAST(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') AS DECIMAL(12,4))
           * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4))),0) - 1.0)   < 0.005 THEN '1.000_exact_engine_no_catmult'
      WHEN ABS(p.selling_price / NULLIF(CAST(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') AS DECIMAL(12,4))
           * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4))),0) - 1.1304) < 0.005 THEN '1.1304_SUSPECT_FACTOR'
      ELSE CONCAT('other_', ROUND(p.selling_price / NULLIF(CAST(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') AS DECIMAL(12,4))
           * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4))),0), 2))
    END AS factor_bucket
  FROM products p
  WHERE p.deleted_at IS NULL
    AND p.currency = 'XOF'
    AND p.selling_price > 0
    AND JSON_EXTRACT(p.pricing_meta,'$.landed_cost') > 0
    AND JSON_EXTRACT(p.pricing_meta,'$.margin_used') IS NOT NULL
) t
GROUP BY factor_bucket
ORDER BY n DESC
LIMIT 40;

-- ---------------------------------------------------------------------
-- Q1c. Persisted category_multiplier census vs category_id
-- ---------------------------------------------------------------------
SELECT
    p.category_id,
    JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS persisted_cm,
    COUNT(*) AS n
FROM products p
WHERE p.deleted_at IS NULL
  AND JSON_EXTRACT(p.pricing_meta,'$.category_multiplier') IS NOT NULL
GROUP BY p.category_id, persisted_cm
ORDER BY n DESC
LIMIT 40;

-- ---------------------------------------------------------------------
-- Q1d. EXPLICIT HYPOTHESIS TESTS over all XOF products with meta.
--      For each candidate factor F, count products whose stored price equals
--      ROUND(landed*(1+margin)*F) within +/-0.5 currency unit (= rounding noise).
--      Hypotheses:
--        H1: persisted pricing_meta.category_multiplier (~1.1304?) ignored by report
--        H2: config category multiplier applied at import but not now (or vice versa)
--        H3: margin-tier misclassification (e.g., 0.40 vs 0.45, 0.20 vs 0.38)
SELECT
  SUM(ABS(selling_price - ROUND(landed*(1+margin))) <= 0.5)                                   AS h_none_factor_1_000,
  SUM(ABS(selling_price - ROUND(landed*(1+0.65))) <= 0.5)                                   AS h_margin_65_nowrongfactor,
  SUM(ABS(selling_price - ROUND(landed*(1+0.55))) <= 0.5)                                   AS h_margin_55_nowrongfactor,
  SUM(ABS(selling_price - ROUND(landed*(1+0.45))) <= 0.5)                                   AS h_margin_45_nowrongfactor,
  SUM(ABS(selling_price - ROUND(landed*(1+0.30))) <= 0.5)                                   AS h_margin_30_nowrongfactor,
  SUM(ABS(selling_price - ROUND(landed*(1+margin)*1.1304)) <= 0.5)                       AS h_1_1304,
  SUM(ABS(selling_price - ROUND(landed*(1+margin)*COALESCE(meta_cm,1))) <= 0.5)          AS h_persisted_meta_cm,
  SUM(ABS(selling_price - ROUND(landed*(1+margin)*1.15)) <= 0.5)                         AS h_1_15_homegarden,
  SUM(ABS(selling_price - ROUND(landed*(1+margin)*1.2))  <= 0.5)                         AS h_1_20_electronics,
  SUM(ABS(selling_price - ROUND(landed*(1+margin)*1.1))  <= 0.5)                         AS h_1_10_clothing_sports,
  SUM(ABS(selling_price - ROUND(landed*(1+margin)*1.25)) <= 0.5)                         AS h_1_25_beauty,
  COUNT(*) AS total_xof_with_meta
FROM (
  SELECT p.id, p.selling_price,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.landed_cost')) AS DECIMAL(12,4)) AS landed,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)) AS meta_cm
  FROM products p
  WHERE p.deleted_at IS NULL AND p.currency='XOF' AND p.selling_price > 0
    AND JSON_EXTRACT(p.pricing_meta,'$.landed_cost') > 0
    AND JSON_EXTRACT(p.pricing_meta,'$.margin_used') IS NOT NULL
) b;

-- Q1e. Per-product detail for the 95 LOWER_THAN_CANONICAL cohort candidates:
--      every product where stored is ~1/1.1304 of landed*(1+margin), with its
--      persisted meta_cm and category_id side by side (does meta_cm == 1.1304?).
SELECT id, code, cost_price, selling_price, landed, margin, meta_cm, category_id,
       ROUND(selling_price / NULLIF(landed*(1+margin),0), 4) AS residual,
       ROUND(landed*(1+margin)) AS canonical_h_none,
       ROUND(landed*(1+margin)*COALESCE(meta_cm,1)) AS canonical_h_metacm
FROM (
  SELECT p.id, p.code, p.cost_price, p.selling_price, p.category_id,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.landed_cost')) AS DECIMAL(12,4)) AS landed,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)) AS meta_cm
  FROM products p
  WHERE p.deleted_at IS NULL AND p.currency='XOF' AND p.selling_price > 0
    AND JSON_EXTRACT(p.pricing_meta,'$.landed_cost') > 0
) x
WHERE ABS(selling_price / NULLIF(landed*(1+margin),0) - 1.1304) < 0.004
   OR ABS(selling_price / NULLIF(landed*(1+margin),0) - 1/1.1304) < 0.004
ORDER BY id
LIMIT 120;

-- ---------------------------------------------------------------------
-- Q2. PRODUCTS 16313 / 16323 — same fields as Q1
-- ---------------------------------------------------------------------
SELECT p.id, p.code, p.cost_price, p.currency, p.selling_price,
       p.category_id, p.supplier_id, p.local_warehouse_id,
       p.pricing_meta, p.source_url, p.cj_lock_price,
       p.created_at, p.updated_at
FROM products p
WHERE p.id IN (16313,16323);

-- ---------------------------------------------------------------------
-- Q3. 1.5x FLOOR SCOPE (product table vs variants table)
-- ---------------------------------------------------------------------
-- Q3a. How many eligible XOF products would have raw canonical < cost*1.5?
SELECT COUNT(*) AS products_where_costx15_gt_canonical
FROM products p
JOIN (SELECT product_id, MAX(weight_grams) wg FROM product_variants GROUP BY product_id) v
  ON v.product_id = p.id
WHERE p.deleted_at IS NULL AND p.currency='XOF' AND p.cost_price > 0
  AND JSON_EXTRACT(p.pricing_meta,'$.landed_cost') > 0
  AND ROUND(CAST(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') AS DECIMAL(12,4))
          * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4))))
      < p.cost_price * 1.5;

-- Q3b. Variants actually floored: variant price == 1.5x product cost exactly?
SELECT COUNT(*) AS variants_at_exactly_1_5x_cost
FROM product_variants pv
JOIN products p ON p.id = pv.product_id
WHERE p.deleted_at IS NULL AND p.cost_price > 0 AND pv.price IS NOT NULL
  AND ABS(pv.price - p.cost_price * 1.5) < 0.01;

-- Q3c. Representative examples: cost | raw canonical | cost*1.5 | stored
SELECT p.id, p.code, p.cost_price,
       ROUND(CAST(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') AS DECIMAL(12,4))
           * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4)))) AS raw_canonical,
       ROUND(p.cost_price * 1.5, 2) AS cost_x_1_5,
       p.selling_price AS stored_product_price,
       (SELECT ROUND(AVG(pv.price),2) FROM product_variants pv WHERE pv.product_id = p.id) AS avg_variant_price
FROM products p
WHERE p.deleted_at IS NULL AND p.currency='XOF' AND p.cost_price > 0
  AND JSON_EXTRACT(p.pricing_meta,'$.landed_cost') > 0
  AND ROUND(CAST(JSON_EXTRACT(p.pricing_meta,'$.landed_cost') AS DECIMAL(12,4))
          * (1 + CAST(JSON_EXTRACT(p.pricing_meta,'$.margin_used') AS DECIMAL(8,4))))
      < p.cost_price * 1.5
ORDER BY p.cost_price DESC
LIMIT 25;

-- ---------------------------------------------------------------------
-- Q4. MISSING_DATA breakdown (mirror of report gates, SELECT-only)
--     NOTE: mirrors PricingCiDiffReport::sourceBucket — supplier_type is used
--     VERBATIM (no 'CJ' default for nulls); nulls bucket via heuristics below.
SELECT reason, COUNT(*) n FROM (
  SELECT CASE
    WHEN p.selling_price IS NULL OR p.selling_price <= 0 THEN 'no_stored_price'
    WHEN p.cost_price IS NULL OR p.cost_price <= 0 THEN 'no_cost_price'
    WHEN COALESCE(
        CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4)),
        (SELECT MAX(pv.weight_grams)/1000 FROM product_variants pv WHERE pv.product_id=p.id AND pv.weight_grams>0)
    ) IS NULL THEN 'no_weight'
    ELSE 'other_or_calculable' END AS reason
  FROM products p
  WHERE p.deleted_at IS NULL
    AND (p.is_active = 1 OR p.status = 'active')
    AND UPPER(COALESCE(NULLIF(TRIM(COALESCE(p.supplier_type,'')),''),
              CASE
                WHEN p.pricing_meta IS NOT NULL OR p.local_warehouse_id IS NOT NULL THEN 'CJ_ENGINE_META'
                WHEN LOWER(p.source_url) LIKE '%cjdropshipping%' THEN 'CJ'
                WHEN LOWER(p.source_url) LIKE '%aliexpress%' THEN 'ALIEXPRESS'
                WHEN LOWER(p.source_url) LIKE '%1688%' THEN '1688'
                WHEN LOWER(p.source_url) LIKE '%woo%' THEN 'WOO'
                ELSE 'MANUAL' END))
        IN ('CJ','ALIEXPRESS','1688','WOO','MANUAL')
) t
WHERE reason <> 'other_or_calculable'
GROUP BY reason ORDER BY n DESC;

-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- Q5. EXCLUDED PRODUCT BREAKDOWN: 19,552 total / 19,176 scanned / 15,562 eligible
-- ---------------------------------------------------------------------
-- Q5a. Not-scanned candidates (soft-deleted or inactive per report filter)
SELECT
  SUM(deleted_at IS NOT NULL) AS soft_deleted,
  SUM(deleted_at IS NULL AND NOT (is_active = 1 OR status='active')) AS inactive_not_scanned,
  COUNT(*) AS grand_total
FROM products;

-- Q5b. Scanned-but-ineligible: supplier_type census EXACTLY as the command
--      buckets it (empty/NULL supplier_type falls through heuristics).
SELECT COALESCE(NULLIF(TRIM(COALESCE(supplier_type,'')),''),'(empty->heuristic)') AS raw_supplier_type,
       COUNT(*) AS n,
       SUM(is_active = 1 OR status='active') AS active_n
FROM products WHERE deleted_at IS NULL
GROUP BY raw_supplier_type ORDER BY n DESC;

-- Q5c. Effective source bucket (exact replica of sourceBucket()) over SCANNED rows,
--      split by eligible vs ineligible against the default --sources list.
SELECT bucket,
       CASE WHEN bucket IN ('CJ','ALIEXPRESS','1688','WOO','MANUAL','CJ') THEN 'ELIGIBLE' ELSE 'INELIGIBLE' END AS elig,
       COUNT(*) n
FROM (
  SELECT CASE
    WHEN TRIM(COALESCE(supplier_type,'')) <> '' THEN UPPER(TRIM(supplier_type))
    WHEN pricing_meta IS NOT NULL OR local_warehouse_id IS NOT NULL THEN 'CJ_ENGINE_META'
    WHEN LOWER(source_url) LIKE '%cjdropshipping%' THEN 'CJ'
    WHEN LOWER(source_url) LIKE '%aliexpress%' THEN 'ALIEXPRESS'
    WHEN LOWER(source_url) LIKE '%1688%' THEN '1688'
    WHEN LOWER(source_url) LIKE '%woo%' THEN 'WOO'
    ELSE 'MANUAL'
  END AS bucket
  FROM products
  WHERE deleted_at IS NULL AND (is_active = 1 OR status='active')
) x
GROUP BY bucket, elig ORDER BY n DESC;

-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- Q6. INPUT FIDELITY: persisted meta rate vs CURRENT warehouse row
--     (detects warehouse-rate drift between import time and now)
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS rate_drift_products,
       ROUND(AVG(lw.shipping_cost_per_kg - CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.shipping_rate_per_kg')) AS DECIMAL(10,4))),4) AS avg_delta
FROM products p
JOIN local_ware_houses lw ON lw.id = p.local_warehouse_id
WHERE p.deleted_at IS NULL
  AND JSON_EXTRACT(p.pricing_meta,'$.shipping_rate_per_kg') IS NOT NULL
  AND ABS(lw.shipping_cost_per_kg - CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.shipping_rate_per_kg')) AS DECIMAL(10,4))) > 0.0001;

-- Q6b. Fidelity: does persisted landed == cost + cj_ship + ext_ship as stored?
--      (detects meta written by a DIFFERENT formula than calculate() uses today)
SELECT COUNT(*) AS landed_recon_mismatch,
       SUM(ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.landed_cost')) AS DECIMAL(12,2))
             - (p.cost_price
                + COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping')) AS DECIMAL(12,2)),0)
                + COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,2)),0)
                * IF(p.currency='XOF',1.05,1))) > 1.5) AS xof_buffer_variant,
       SUM(ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.landed_cost')) AS DECIMAL(12,2))
             - (p.cost_price
                + COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping')) AS DECIMAL(12,2)),0)
                + COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,2)),0))) <= 1.5) AS no_buffer_variant
FROM products p
WHERE p.deleted_at IS NULL AND p.currency='XOF'
  AND JSON_EXTRACT(p.pricing_meta,'$.landed_cost') IS NOT NULL
  AND p.cost_price > 0;


-- ---------------------------------------------------------------------
-- Q8. INPUT FIDELITY: would resolvePricingWarehouse() TODAY pick the SAME
--     warehouse that is persisted in products.local_warehouse_id?
--     (replica of CjProductImportService::resolvePricingWarehouse fallback
--      chain for rows with no country signal: cheapest-rate default-first)
-- ---------------------------------------------------------------------
SELECT
  CASE WHEN p.local_warehouse_id <=> w_today.warehouse_id THEN 'same' ELSE 'DIFFERENT' END AS fidelity,
  COUNT(*) n
FROM products p
CROSS JOIN (
  SELECT id AS warehouse_id
  FROM local_ware_houses
  ORDER BY is_default DESC,
           CASE WHEN shipping_cost_per_kg IS NULL OR shipping_cost_per_kg <= 0 THEN 1 ELSE 0 END,
           shipping_cost_per_kg
  LIMIT 1
) w_today
WHERE p.deleted_at IS NULL AND p.currency='XOF' AND p.pricing_meta IS NOT NULL
GROUP BY fidelity;

-- Q8b. Warehouse distribution actually used by the catalog
SELECT lw.id, lw.name, lw.country, lw.is_default, lw.shipping_cost_per_kg, lw.shipping_base_cost,
       lw.shipping_additional_cost, lw.shipping_min_charge,
       COUNT(p.id) AS products_using
FROM local_ware_houses lw
LEFT JOIN products p ON p.local_warehouse_id = lw.id AND p.deleted_at IS NULL
GROUP BY lw.id, lw.name, lw.country, lw.is_default, lw.shipping_cost_per_kg, lw.shipping_base_cost,
         lw.shipping_additional_cost, lw.shipping_min_charge
ORDER BY products_using DESC;

-- Q8c. Do pricing_meta.warehouse_id and products.local_warehouse_id ever disagree?
SELECT COUNT(*) AS meta_vs_column_mismatch
FROM products p
WHERE p.deleted_at IS NULL
  AND JSON_EXTRACT(p.pricing_meta,'$.warehouse_id') IS NOT NULL
  AND p.local_warehouse_id IS NOT NULL
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.warehouse_id')) AS UNSIGNED) <> p.local_warehouse_id;


-- ---------------------------------------------------------------------
-- Q9. RAW pricing_meta JSON for the ten suspect products (unit ground-truth)
-- ---------------------------------------------------------------------
SELECT id, code, cost_price, selling_price, currency, category_id,
       local_warehouse_id, pricing_meta
FROM products
WHERE id IN (28,1247,42,1246,33,17,51,73,1248,1256)
ORDER BY id;

-- ---------------------------------------------------------------------
-- Q7. Re-run baseline (MUST equal Q0 exactly — read-only proof)
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS product_count,
       MAX(updated_at) AS max_updated_at,
       ROUND(SUM(selling_price), 2) AS selling_price_sum
FROM products;
