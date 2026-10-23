-- =====================================================================
-- DRY-RUN: REMOVING external_shipping FROM CI PRODUCT PRICING
-- READ-ONLY. SELECT statements only. No writes, no jobs, no reprice.
-- Independent recomputation from RAW persisted inputs (cost, weight,
-- margin_used, category_multiplier) -- does NOT trust landed_cost meta.
-- Production semantics confirmed in PricingService::calculate():
--   price = round( (cost + cj_ship + ext_ship) * (1+margin) , 2 )
--           then if category_id present: round( price * multiplier , 2 )
-- Target formula (business decision under evaluation):
--   new   = round( (cost + cj_ship) * (1+margin), 2 )
--           then round( new * multiplier, 2 )
-- NOTE: $15/kg is NOT re-added anywhere; we simply drop ext_ship.
-- =====================================================================

-- Q0. INDEPENDENT BASELINE (must equal final Q9 exactly)
SELECT COUNT(*) AS product_count,
       MAX(updated_at) AS max_updated_at,
       ROUND(SUM(selling_price),2) AS selling_price_sum
FROM products;

-- ---------------------------------------------------------------------
-- Q1. POPULATION SEGMENTATION (Step 1)
-- "CI catalog" here = active, non-deleted rows bucketed as CJ by the
-- C1 report logic (supplier_type='cj' OR heuristic CJ_ENGINE_META).
-- ---------------------------------------------------------------------
WITH scoped AS (
  SELECT p.*,
         CASE WHEN UPPER(TRIM(COALESCE(p.supplier_type,'')))='CJ'
                OR (COALESCE(TRIM(p.supplier_type),'')='' AND (p.pricing_meta IS NOT NULL OR p.local_warehouse_id IS NOT NULL))
              THEN 1 ELSE 0 END AS is_cj_bucket,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,4)) AS ext_ship,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping'))      AS DECIMAL(12,4)) AS cj_ship,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg'))        AS DECIMAL(10,4)) AS wkg,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used'))      AS DECIMAL(8,4))  AS margin,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)) AS meta_cm
  FROM products p
  WHERE p.deleted_at IS NULL
)
SELECT
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active'))                       AS total_active_ci_products,
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active') AND ext_ship>0)        AS with_external_shipping,
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active') AND ext_ship=0)        AS ext_ship_zero,
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active') AND ext_ship IS NULL)  AS ext_ship_null,
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active')
      AND cost_price>0 AND wkg>0 AND selling_price>0 AND margin IS NOT NULL
      AND ext_ship>0)                                                            AS eligible_for_recalc,
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active') AND (cost_price IS NULL OR cost_price<=0)) AS missing_cost,
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active') AND (wkg IS NULL OR wkg<=0))               AS missing_weight,
  SUM(is_cj_bucket=1 AND (is_active=1 OR status='active') AND (selling_price IS NULL OR selling_price<=0)) AS missing_stored_price,
  SUM(is_cj_bucket=1 AND deleted_at IS NULL AND NOT (is_active=1 OR status='active'))                AS inactive_or_draft_ci,
  SUM(deleted_at IS NOT NULL)                                                                        AS soft_deleted_all
FROM scoped;

-- ---------------------------------------------------------------------
-- Q2. IMPACT SUMMARY over eligible affected population (Steps 2-3)
-- Independent path: raw inputs -> two-step rounding replicating PHP.
-- ---------------------------------------------------------------------
WITH base AS (
  SELECT p.id, p.code, p.cost_price, p.currency, p.selling_price, p.category_id,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,4)) AS ext_ship,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping')) AS DECIMAL(12,4)),0) AS cj_ship,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4)) AS wkg,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         COALESCE(
           CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)),
           CASE p.category_id WHEN 1 THEN 1.20 WHEN 2 THEN 1.10 WHEN 3 THEN 1.15
                              WHEN 4 THEN 1.25 WHEN 5 THEN 1.10 ELSE 1.00 END
         ) AS cm
  FROM products p
  WHERE p.deleted_at IS NULL
    AND (p.is_active=1 OR p.status='active')
    AND p.cost_price>0 AND p.selling_price>0
    AND (UPPER(TRIM(COALESCE(p.supplier_type,'')))='CJ'
         OR (COALESCE(TRIM(p.supplier_type),'')='' AND (p.pricing_meta IS NOT NULL OR p.local_warehouse_id IS NOT NULL)))
), calc AS (
  SELECT b.*,
         ROUND((b.cost_price + b.cj_ship + b.ext_ship) * (1+b.margin), 2) AS recanon_step1,
         ROUND((b.cost_price + b.cj_ship)             * (1+b.margin), 2) AS new_step1
  FROM base b
  WHERE b.ext_ship > 0 AND b.wkg > 0 AND b.margin IS NOT NULL
), full_prices AS (
  SELECT c.*,
         ROUND(c.recanon_step1 * c.cm, 2) AS canonical_price,   -- same formula WITH ext
         ROUND(c.new_step1     * c.cm, 2) AS new_price          -- target formula WITHOUT ext
  FROM calc c
)
SELECT
  COUNT(*)                                                        AS n_eligible_with_ext_ship,
  ROUND(AVG((selling_price - new_price)/NULLIF(selling_price,0)*100),3) AS avg_reduction_pct,
  ROUND(MIN((selling_price - new_price)/NULLIF(selling_price,0)*100),3) AS min_reduction_pct,
  ROUND(MAX((selling_price - new_price)/NULLIF(selling_price,0)*100),3) AS max_reduction_pct,
  ROUND(SUM(selling_price),2)                                     AS total_current_sum,
  ROUND(SUM(new_price),2)                                         AS total_new_sum,
  ROUND(SUM(selling_price)-SUM(new_price),2)                      AS total_abs_reduction,
  SUM(new_price > selling_price + 0.01)                           AS n_new_higher_than_stored,
  SUM(ABS(new_price - selling_price) <= 0.01)                     AS n_no_change,
  ROUND(STDDEV_POP((canonical_price-selling_price)/NULLIF(selling_price,0)*100),2) AS canon_vs_stored_sigma_pct
FROM full_prices;

-- Median reduction % (MySQL8 window; exact on ordered set)
WITH base AS (
  SELECT p.id, p.selling_price,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,4)) AS ext_ship,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping')) AS DECIMAL(12,4)),0) AS cj_ship,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4)) AS wkg,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)),
                  CASE p.category_id WHEN 1 THEN 1.20 WHEN 2 THEN 1.10 WHEN 3 THEN 1.15
                                     WHEN 4 THEN 1.25 WHEN 5 THEN 1.10 ELSE 1.00 END) AS cm
  FROM products p
  WHERE p.deleted_at IS NULL AND (p.is_active=1 OR p.status='active')
    AND p.cost_price>0 AND p.selling_price>0
    AND (UPPER(TRIM(COALESCE(p.supplier_type,'')))='CJ'
         OR (COALESCE(TRIM(p.supplier_type),'')='' AND (p.pricing_meta IS NOT NULL OR p.local_warehouse_id IS NOT NULL)))
), r AS (
  SELECT ROUND((selling_price - ROUND(ROUND((cost_price+cj_ship)*(1+margin),2)*cm,2))/selling_price*100, 4) AS red_pct,
         ROW_NUMBER() OVER (ORDER BY ROUND((selling_price - ROUND(ROUND((cost_price+cj_ship)*(1+margin),2)*cm,2))/selling_price*100)) rn,
         COUNT(*) OVER () cnt
  FROM base WHERE ext_ship>0 AND wkg>0 AND margin IS NOT NULL
)
SELECT ROUND(AVG(red_pct),3) AS median_reduction_pct FROM r WHERE rn IN (FLOOR((cnt+1)/2), CEIL((cnt+1)/2));

-- ---------------------------------------------------------------------
-- Q3. 20-PRODUCT SAMPLE across weight tiers (Step 5)
-- ---------------------------------------------------------------------
WITH base AS (
  SELECT p.id, p.code, p.weight, p.cost_price, p.selling_price, p.category_id,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,4)) AS ext_ship,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping')) AS DECIMAL(12,4)),0) AS cj_ship,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4)) AS wkg,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)),
                  CASE p.category_id WHEN 1 THEN 1.20 WHEN 2 THEN 1.10 WHEN 3 THEN 1.15
                                     WHEN 4 THEN 1.25 WHEN 5 THEN 1.10 ELSE 1.00 END) AS cm
  FROM products p
  WHERE p.deleted_at IS NULL AND (p.is_active=1 OR p.status='active')
    AND p.cost_price>0 AND p.selling_price>0
    AND (UPPER(TRIM(COALESCE(p.supplier_type,'')))='CJ'
         OR (COALESCE(TRIM(p.supplier_type),'')='' AND (p.pricing_meta IS NOT NULL OR p.local_warehouse_id IS NOT NULL)))
), calc AS (
  SELECT b.*, ROUND(ROUND((cost_price+cj_ship)*(1+margin),2)*cm,2) AS new_price,
         CASE WHEN wkg<=0.5 THEN 'T1_le_0.5kg' WHEN wkg<=1.0 THEN 'T2_le_1kg'
              WHEN wkg<=2.0 THEN 'T3_le_2kg' ELSE 'T4_gt_2kg' END AS tier,
         ROW_NUMBER() OVER (PARTITION BY CASE WHEN wkg<=0.5 THEN 1 WHEN wkg<=1.0 THEN 2
                                        WHEN wkg<=2.0 THEN 3 ELSE 4 END ORDER BY id) rn
  FROM base b WHERE ext_ship>0 AND wkg>0 AND margin IS NOT NULL
)
SELECT id, code, tier, wkg AS weight_kg, cost_price, cj_ship AS cj_shipping, ext_ship AS external_shipping,
       margin AS weight_margin, cm AS category_multiplier,
       selling_price AS current_price, new_price,
       ROUND(selling_price-new_price,2) AS reduction,
       ROUND((selling_price-new_price)/selling_price*100,2) AS reduction_pct
FROM calc WHERE rn<=5 ORDER BY tier, id;

-- ---------------------------------------------------------------------
-- Q4. EDGE CASES (Step 4)
-- ---------------------------------------------------------------------
-- Q4a. largest reductions (top 10 absolute)
WITH base AS (
  SELECT p.id, p.code, p.selling_price, p.cost_price,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,4)) AS ext_ship,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping')) AS DECIMAL(12,4)),0) AS cj_ship,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4)) AS wkg,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)),
                  CASE p.category_id WHEN 1 THEN 1.20 WHEN 2 THEN 1.10 WHEN 3 THEN 1.15
                                     WHEN 4 THEN 1.25 WHEN 5 THEN 1.10 ELSE 1.00 END) AS cm
  FROM products p
  WHERE p.deleted_at IS NULL AND (p.is_active=1 OR p.status='active')
    AND p.cost_price>0 AND p.selling_price>0
    AND (UPPER(TRIM(COALESCE(p.supplier_type,'')))='CJ'
         OR (COALESCE(TRIM(p.supplier_type),'')='' AND (p.pricing_meta IS NOT NULL OR p.local_warehouse_id IS NOT NULL)))
)
SELECT id, code, wkg, cost_price, cj_ship, ext_ship, margin, cm, selling_price AS current_price,
       ROUND(ROUND((cost_price+cj_ship)*(1+margin),2)*cm,2) AS new_price,
       ROUND(selling_price-ROUND(ROUND((cost_price+cj_ship)*(1+margin),2)*cm,2),2) AS reduction
FROM base WHERE ext_ship>0 AND wkg>0 AND margin IS NOT NULL
ORDER BY reduction DESC LIMIT 10;

-- Q4b. suspicious: recalculated-with-ext (canonical) deviates >5% from stored
WITH base AS (
  SELECT p.id, p.code, p.selling_price, p.cost_price, p.category_id,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.external_shipping')) AS DECIMAL(12,4)) AS ext_ship,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.cj_shipping')) AS DECIMAL(12,4)),0) AS cj_ship,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4)) AS wkg,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.category_multiplier')) AS DECIMAL(6,4)),
                  CASE p.category_id WHEN 1 THEN 1.20 WHEN 2 THEN 1.10 WHEN 3 THEN 1.15
                                     WHEN 4 THEN 1.25 WHEN 5 THEN 1.10 ELSE 1.00 END) AS cm
  FROM products p
  WHERE p.deleted_at IS NULL AND (p.is_active=1 OR p.status='active')
    AND p.cost_price>0 AND p.selling_price>0
    AND (UPPER(TRIM(COALESCE(p.supplier_type,'')))='CJ'
         OR (COALESCE(TRIM(p.supplier_type),'')='' AND (p.pricing_meta IS NOT NULL OR p.local_warehouse_id IS NOT NULL)))
)
SELECT COUNT(*) AS suspicious_canon_deviation_gt5pct
FROM base
WHERE ext_ship>0 AND wkg>0 AND margin IS NOT NULL
  AND ABS(ROUND(ROUND((cost_price+cj_ship+ext_ship)*(1+margin),2)*cm,2) - selling_price)
      / NULLIF(selling_price,0) > 0.05;

-- Q4c. margin_used vs CURRENT config tier disagreement (persisted-vs-now check)
WITH base AS (
  SELECT p.id,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4)) AS wkg,
         CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.margin_used')) AS DECIMAL(8,4)) AS margin,
         CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4))<=0.5 THEN 0.65
              WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4))<=1.0 THEN 0.55
              WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(p.pricing_meta,'$.weight_kg')) AS DECIMAL(10,4))<=2.0 THEN 0.45
              ELSE 0.30 END AS margin_now
  FROM products p
  WHERE p.deleted_at IS NULL AND (p.is_active=1 OR p.status='active')
    AND JSON_EXTRACT(p.pricing_meta,'$.margin_used') IS NOT NULL
    AND JSON_EXTRACT(p.pricing_meta,'$.weight_kg') IS NOT NULL
)
SELECT COUNT(*) AS margin_tier_drift_count,
       SUM(margin<>margin_now) AS n_persisted_ne_now
FROM base WHERE ABS(margin-margin_now)>0.0001;

-- Q9. RE-BASELINE (must equal Q0 exactly — read-only proof)
SELECT COUNT(*) AS product_count,
       MAX(updated_at) AS max_updated_at,
       ROUND(SUM(selling_price),2) AS selling_price_sum
FROM products;
