-- Enhanced CJ Product Repricing Query - Replicates PricingService Logic
-- Single query that matches the sophisticated weight-based pricing engine
-- Compatible with MariaDB syntax

-- =============================================
-- ENHANCED PROCEDURE: calculate_cj_product_pricing_advanced
-- =============================================
CREATE PROCEDURE calculate_cj_product_pricing_advanced(
    IN p_product_id BIGINT,
    IN p_currency VARCHAR(3),
    IN p_margin_percentage DECIMAL(5,2), -- Fallback margin if weight-based not configured
    IN p_default_shipping_per_kg DECIMAL(10,2), -- Default shipping rate (e.g., 7.00)
    IN p_force_update BOOLEAN DEFAULT FALSE,
    OUT p_success BOOLEAN,
    OUT p_message TEXT,
    OUT p_final_price DECIMAL(12,2),
    OUT p_landed_cost DECIMAL(12,2),
    OUT p_shipping_cost DECIMAL(12,2),
    OUT p_margin_used DECIMAL(5,2)
)
BEGIN
    DECLARE v_cost_price DECIMAL(12,2) DEFAULT 0;
    DECLARE v_weight_grams DECIMAL(10,3) DEFAULT 0;
    DECLARE v_weight_kg DECIMAL(10,4) DEFAULT 0;
    DECLARE v_cj_shipping DECIMAL(12,2) DEFAULT 0;
    DECLARE v_external_shipping DECIMAL(12,2) DEFAULT 0;
    DECLARE v_warehouse_id BIGINT;
    DECLARE v_warehouse_rate_per_kg DECIMAL(10,2) DEFAULT 0;
    DECLARE v_margin_from_weight DECIMAL(5,2) DEFAULT 0;
    DECLARE v_min_margin_percent DECIMAL(5,2) DEFAULT 45.0;
    DECLARE v_has_variants INT DEFAULT 0;
    DECLARE v_corruption_threshold DECIMAL(12,2);
    DECLARE v_reasonable_threshold DECIMAL(12,2);
    DECLARE v_min_selling_price DECIMAL(12,2);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
        p_message = MESSAGE_TEXT;
        SET p_success = FALSE;
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Get product data including weight from variants if needed
    SELECT 
        COALESCE(p.cost_price, 0),
        COALESCE(p.cj_shipping_amount, 0),
        COALESCE(p.local_warehouse_id, (
            SELECT id FROM local_ware_houses 
            WHERE is_default = TRUE 
            ORDER BY CASE WHEN country = 'CN' THEN 1 ELSE 2 END, id 
            LIMIT 1
        ))
    INTO v_cost_price, v_cj_shipping, v_warehouse_id
    FROM products p
    WHERE p.id = p_product_id AND p.cj_pid IS NOT NULL;
    
    IF v_cost_price = 0 THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Product ', p_product_id, ' not found or has no cost price');
        ROLLBACK;
        LEAVE;
    END IF;
    
    -- Extract weight: try product weight first, then lightest variant
    SELECT COALESCE(
        (SELECT JSON_EXTRACT(attributes, '$.productWeight') FROM products WHERE id = p_product_id),
        (SELECT MIN(CAST(JSON_EXTRACT(attributes, '$.variantWeight' AS DECIMAL(10,3)))
         FROM product_variants 
         WHERE product_id = p_product_id 
         AND JSON_EXTRACT(attributes, '$.variantWeight') IS NOT NULL)
    ) INTO v_weight_grams;
    
    -- Convert to kg (handle grams to kg conversion)
    SET v_weight_kg = COALESCE(v_weight_grams, 0) / 1000.0;
    
    -- Get warehouse shipping rate
    SELECT COALESCE(shipping_cost_per_kg, p_default_shipping_per_kg)
    INTO v_warehouse_rate_per_kg
    FROM local_ware_houses 
    WHERE id = v_warehouse_id;
    
    -- Calculate weight-based margin (replicates PricingService::resolveMarginByWeight logic)
    SET v_margin_from_weight = CASE
        -- Light items (≤100g): Higher margin
        WHEN v_weight_kg <= 0.1 THEN 50.0
        -- Light items (≤500g): Good margin  
        WHEN v_weight_kg <= 0.5 THEN 40.0
        -- Medium items (≤1kg): Standard margin
        WHEN v_weight_kg <= 1.0 THEN 35.0
        -- Heavy items (≤2kg): Lower margin
        WHEN v_weight_kg <= 2.0 THEN 30.0
        -- Very heavy items (>2kg): Lowest margin
        ELSE 25.0
    END;
    
    -- Use weight-based margin unless overridden
    IF p_margin_percentage IS NOT NULL AND p_margin_percentage > 0 THEN
        SET p_margin_used = p_margin_percentage;
    ELSE
        SET p_margin_used = v_margin_from_weight;
    END IF;
    
    -- Calculate shipping costs (replicates PricingService::calculate logic)
    SET v_external_shipping = ROUND(v_weight_kg * v_warehouse_rate_per_kg, 4);
    SET v_shipping_cost = v_cj_shipping + v_external_shipping;
    
    -- Calculate landed cost (replicates PricingService landed cost calculation)
    SET v_landed_cost = v_cost_price + v_shipping_cost;
    
    -- Calculate selling price with margin
    SET p_final_price = ROUND(v_landed_cost * (1 + p_margin_used / 100), 2);
    
    -- Apply minimum margin (45% by default, replicates PricingService min margin)
    SET v_min_selling_price = ROUND(v_landed_cost * (1 + v_min_margin_percent / 100), 2);
    IF p_final_price < v_min_selling_price THEN
        SET p_final_price = v_min_selling_price;
        SET p_message = CONCAT('Applied minimum margin: price adjusted to ', p_final_price);
    END IF;
    
    -- Corruption detection (15x markup threshold, replicates service validation)
    SET v_corruption_threshold = v_cost_price * 15.0;
    IF p_final_price > v_corruption_threshold THEN
        SET p_final_price = v_min_selling_price;
        SET p_message = CONCAT('CORRUPTION: Price exceeded 15x threshold, forced to minimum: ', p_final_price);
    END IF;
    
    -- Reasonable price check (10x markup threshold)
    SET v_reasonable_threshold = v_cost_price * 10.0;
    IF p_final_price > v_reasonable_threshold THEN
        SET p_final_price = v_reasonable_threshold;
        SET p_message = CONCAT('WARNING: Price exceeded reasonable threshold, capped at: ', p_final_price);
    END IF;
    
    -- Update product with comprehensive pricing metadata (replicates service JSON structure)
    UPDATE products 
    SET selling_price = p_final_price,
        local_warehouse_id = v_warehouse_id,
        pricing_meta = JSON_OBJECT(
            'currency', p_currency,
            'margin_percent', p_margin_used,
            'margin_source', CASE 
                WHEN p_margin_percentage IS NOT NULL THEN 'manual'
                ELSE 'weight_based'
            END,
            'cost_price', v_cost_price,
            'weight_kg', ROUND(v_weight_kg, 4),
            'weight_grams', v_weight_grams,
            'cj_shipping', ROUND(v_cj_shipping, 2),
            'external_shipping', ROUND(v_external_shipping, 2),
            'shipping_rate_per_kg', v_warehouse_rate_per_kg,
            'shipping_cost', ROUND(v_shipping_cost, 2),
            'landed_cost', ROUND(v_landed_cost, 2),
            'final_price', p_final_price,
            'min_price', v_min_selling_price,
            'warehouse_id', v_warehouse_id,
            'calculated_at', NOW(),
            'corruption_threshold', v_corruption_threshold,
            'reasonable_threshold', v_reasonable_threshold
        ),
        updated_at = NOW()
    WHERE id = p_product_id;
    
    -- Check if product has variants and update them too
    SELECT COUNT(*) INTO v_has_variants
    FROM product_variants 
    WHERE product_id = p_product_id;
    
    IF v_has_variants > 0 THEN
        -- Update variants with proportional pricing based on their costs
        UPDATE product_variants pv
        SET 
            price = CASE 
                WHEN pv.cost_price > 0 THEN 
                    ROUND(
                        (pv.cost_price + (pv.cost_price / v_cost_price) * v_shipping_cost) * 
                        (1 + p_margin_used / 100), 2
                    )
                ELSE p_final_price
            END,
            updated_at = NOW()
        WHERE pv.product_id = p_product_id;
    END IF;
    
    SET p_success = TRUE;
    IF p_message IS NULL OR p_message = '' THEN
        SET p_message = CONCAT(
            'Product ', p_product_id, ' priced successfully: cost=', v_cost_price,
            ', weight=', ROUND(v_weight_kg, 4), 'kg, shipping=', ROUND(v_shipping_cost, 2),
            ', margin=', p_margin_used, '%, final=', p_final_price
        );
    END IF;
    
    COMMIT;
END;

-- =============================================
-- USAGE EXAMPLES
-- =============================================

-- Example 1: Basic usage with weight-based margins
/*
CALL calculate_cj_product_pricing_advanced(
    123,                    -- product_id
    'USD',                  -- currency
    NULL,                   -- margin_percentage (NULL = use weight-based)
    7.00,                   -- default_shipping_per_kg
    FALSE,                  -- force_update
    @success, @message, @final_price, @landed_cost, @shipping_cost, @margin_used
);

SELECT @success, @message, @final_price, @landed_cost, @shipping_cost, @margin_used;
*/

-- Example 2: Override with specific margin
/*
CALL calculate_cj_product_pricing_advanced(
    123, 'USD', 30.0, 7.00, FALSE,
    @success, @message, @final_price, @landed_cost, @shipping_cost, @margin_used
);
*/

-- Example 3: Batch processing multiple products
/*
DROP TEMPORARY TABLE IF EXISTS batch_results;
CREATE TEMPORARY TABLE batch_results (
    product_id BIGINT,
    success BOOLEAN,
    message TEXT,
    final_price DECIMAL(12,2),
    landed_cost DECIMAL(12,2),
    shipping_cost DECIMAL(12,2),
    margin_used DECIMAL(5,2),
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Process products in a loop (you can wrap this in application code)
INSERT INTO batch_results (product_id, success, message, final_price, landed_cost, shipping_cost, margin_used)
SELECT 
    p.id,
    NULL, NULL, NULL, NULL, NULL, NULL
FROM products p 
WHERE p.cj_pid IS NOT NULL 
AND (p.pricing_meta IS NULL OR p.pricing_meta = '{}')
LIMIT 100;

-- Then update each one (application code would loop through the results)
*/
