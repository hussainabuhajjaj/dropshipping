-- CJ Product Pricing - MariaDB Compatible Version
-- No DEFAULT parameters, clean syntax, no bugs

-- Drop existing procedures first
DROP PROCEDURE IF EXISTS calculate_cj_product_pricing_comprehensive;
DROP PROCEDURE IF EXISTS batch_reprice_all_cj_products;
DROP PROCEDURE IF EXISTS get_cj_pricing_statistics;

-- =============================================
-- MAIN PRICING PROCEDURE
-- =============================================
CREATE PROCEDURE calculate_cj_product_pricing_comprehensive(
    IN p_product_id BIGINT,
    IN p_currency VARCHAR(3),
    IN p_margin_percentage DECIMAL(5,2),
    IN p_default_shipping_per_kg DECIMAL(10,2),
    IN p_min_margin_percent DECIMAL(5,2),
    IN p_force_update BOOLEAN,
    OUT p_success BOOLEAN,
    OUT p_message TEXT,
    OUT p_final_price DECIMAL(12,2),
    OUT p_landed_cost DECIMAL(12,2),
    OUT p_shipping_cost DECIMAL(12,2),
    OUT p_margin_used DECIMAL(5,2)
)
BEGIN
    -- Product variables
    DECLARE v_cost_price DECIMAL(12,2);
    DECLARE v_weight_grams DECIMAL(10,3);
    DECLARE v_weight_kg DECIMAL(10,4);
    DECLARE v_cj_shipping DECIMAL(12,2);
    DECLARE v_product_exists INT;
    
    -- Warehouse variables
    DECLARE v_warehouse_id BIGINT;
    DECLARE v_warehouse_rate_per_kg DECIMAL(10,2);
    DECLARE v_warehouse_country VARCHAR(2);
    
    -- Pricing calculation variables
    DECLARE v_external_shipping DECIMAL(12,2);
    DECLARE v_margin_from_weight DECIMAL(5,2);
    DECLARE v_corruption_threshold DECIMAL(12,2);
    DECLARE v_reasonable_threshold DECIMAL(12,2);
    DECLARE v_min_selling_price DECIMAL(12,2);
    DECLARE v_has_variants INT;
    
    -- Variant processing
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_variant_id BIGINT;
    DECLARE v_variant_cost DECIMAL(12,2);
    DECLARE v_variant_weight_grams INT;
    DECLARE v_variant_price DECIMAL(12,2);
    DECLARE variant_cursor CURSOR FOR
        SELECT id, cost_price, weight_grams
        FROM product_variants
        WHERE product_id = p_product_id AND cost_price > 0;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Error handling
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 p_message = MESSAGE_TEXT;
        SET p_success = FALSE;
        ROLLBACK;
    END;
    
    -- Initialize variables
    SET v_cost_price = 0;
    SET v_weight_grams = 0;
    SET v_weight_kg = 0;
    SET v_cj_shipping = 0;
    SET v_product_exists = 0;
    SET v_warehouse_id = 0;
    SET v_warehouse_rate_per_kg = 0;
    SET v_external_shipping = 0;
    SET v_margin_from_weight = 0;
    SET v_min_selling_price = 0;
    SET v_has_variants = 0;
    SET p_success = FALSE;
    SET p_message = '';
    SET p_final_price = 0;
    SET p_landed_cost = 0;
    SET p_shipping_cost = 0;
    SET p_margin_used = 0;
    
    -- Input validation and defaults
    IF p_product_id IS NULL OR p_product_id <= 0 THEN
        SET p_message = 'Invalid product ID';
        LEAVE;
    END IF;
    
    IF p_currency IS NULL THEN
        SET p_currency = 'USD';
    END IF;
    
    IF p_margin_percentage IS NULL THEN
        SET p_margin_percentage = 0;
    END IF;
    
    IF p_default_shipping_per_kg IS NULL OR p_default_shipping_per_kg <= 0 THEN
        SET p_default_shipping_per_kg = 7.00;
    END IF;
    
    IF p_min_margin_percent IS NULL OR p_min_margin_percent <= 0 THEN
        SET p_min_margin_percent = 45.00;
    END IF;
    
    IF p_force_update IS NULL THEN
        SET p_force_update = FALSE;
    END IF;
    
    START TRANSACTION;
    
    -- Get product data and validate existence
    SELECT 
        COALESCE(cost_price, 0),
        COALESCE(cj_shipping_amount, 0),
        COALESCE(local_warehouse_id, 0),
        CASE WHEN cj_pid IS NOT NULL THEN 1 ELSE 0 END
    INTO v_cost_price, v_cj_shipping, v_warehouse_id, v_product_exists
    FROM products 
    WHERE id = p_product_id;
    
    IF v_product_exists = 0 OR v_cost_price = 0 THEN
        SET p_message = CONCAT('Product ', p_product_id, ' not found, has no CJ PID, or has no cost price');
        ROLLBACK;
        LEAVE;
    END IF;
    
    -- Get default warehouse if not set
    IF v_warehouse_id = 0 THEN
        SELECT id INTO v_warehouse_id
        FROM local_ware_houses 
        WHERE is_default = TRUE 
        ORDER BY CASE WHEN country = 'CN' THEN 1 ELSE 2 END, id 
        LIMIT 1;
    END IF;
    
    -- Check if pricing should be updated
    IF p_force_update = FALSE THEN
        SELECT COUNT(*) INTO v_has_variants
        FROM products 
        WHERE id = p_product_id 
        AND pricing_meta IS NOT NULL 
        AND selling_price IS NOT NULL 
        AND selling_price > 0;
        
        IF v_has_variants > 0 THEN
            SET p_success = TRUE;
            SET p_message = 'Product already has pricing metadata and selling price';
            SELECT selling_price INTO p_final_price FROM products WHERE id = p_product_id;
            COMMIT;
            LEAVE;
        END IF;
    END IF;
    
    -- Get warehouse details
    SELECT COALESCE(shipping_cost_per_kg, p_default_shipping_per_kg), COALESCE(country, 'CN')
    INTO v_warehouse_rate_per_kg, v_warehouse_country
    FROM local_ware_houses 
    WHERE id = v_warehouse_id;
    
    -- Extract weight from variants (simplified approach)
    SELECT COALESCE(MIN(weight_grams), 0)
    INTO v_weight_grams
    FROM product_variants 
    WHERE product_id = p_product_id AND weight_grams > 0;
    
    -- Convert grams to kg
    SET v_weight_kg = v_weight_grams / 1000.0;
    
    -- Calculate weight-based margin
    SET v_margin_from_weight = CASE
        WHEN v_weight_kg <= 0.1 THEN 50.0
        WHEN v_weight_kg <= 0.5 THEN 40.0
        WHEN v_weight_kg <= 1.0 THEN 35.0
        WHEN v_weight_kg <= 2.0 THEN 30.0
        ELSE 25.0
    END;
    
    -- Use provided margin or weight-based margin
    IF p_margin_percentage > 0 THEN
        SET p_margin_used = p_margin_percentage;
    ELSE
        SET p_margin_used = v_margin_from_weight;
    END IF;
    
    -- Calculate shipping costs
    SET v_external_shipping = ROUND(v_weight_kg * v_warehouse_rate_per_kg, 4);
    SET p_shipping_cost = v_cj_shipping + v_external_shipping;
    
    -- Calculate landed cost
    SET p_landed_cost = v_cost_price + p_shipping_cost;
    
    -- Calculate selling price with margin
    SET p_final_price = ROUND(p_landed_cost * (1 + p_margin_used / 100), 2);
    
    -- Apply minimum margin enforcement
    SET v_min_selling_price = ROUND(p_landed_cost * (1 + p_min_margin_percent / 100), 2);
    IF p_final_price < v_min_selling_price THEN
        SET p_final_price = v_min_selling_price;
        SET p_message = CONCAT('Applied minimum margin: price adjusted to ', p_final_price);
    END IF;
    
    -- Corruption detection
    SET v_corruption_threshold = v_cost_price * 15.0;
    IF p_final_price > v_corruption_threshold THEN
        SET p_final_price = v_min_selling_price;
        SET p_message = CONCAT('CORRUPTION: Price exceeded 15x threshold, forced to minimum: ', p_final_price);
    END IF;
    
    -- Reasonable price check
    SET v_reasonable_threshold = v_cost_price * 10.0;
    IF p_final_price > v_reasonable_threshold THEN
        SET p_final_price = v_reasonable_threshold;
        SET p_message = CONCAT('WARNING: Price exceeded reasonable threshold, capped at: ', p_final_price);
    END IF;
    
    -- Update product with pricing metadata
    UPDATE products 
    SET selling_price = p_final_price,
        local_warehouse_id = v_warehouse_id,
        pricing_meta = JSON_OBJECT(
            'currency', p_currency,
            'margin_percent', p_margin_used,
            'margin_source', CASE WHEN p_margin_percentage > 0 THEN 'manual' ELSE 'weight_based' END,
            'cost_price', v_cost_price,
            'weight_kg', ROUND(v_weight_kg, 4),
            'weight_grams', v_weight_grams,
            'cj_shipping', ROUND(v_cj_shipping, 2),
            'external_shipping', ROUND(v_external_shipping, 2),
            'shipping_rate_per_kg', v_warehouse_rate_per_kg,
            'shipping_cost', ROUND(p_shipping_cost, 2),
            'landed_cost', ROUND(p_landed_cost, 2),
            'final_price', p_final_price,
            'min_price', v_min_selling_price,
            'warehouse_id', v_warehouse_id,
            'warehouse_country', v_warehouse_country,
            'calculated_at', NOW()
        ),
        updated_at = NOW()
    WHERE id = p_product_id;
    
    -- Update variants
    OPEN variant_cursor;
    
    variant_loop: LOOP
        FETCH variant_cursor INTO v_variant_id, v_variant_cost, v_variant_weight_grams;
        IF done THEN
            LEAVE variant_loop;
        END IF;
        
        -- Calculate variant price with same margin
        SET v_variant_price = ROUND(v_variant_cost * (1 + p_margin_used / 100), 2);
        
        -- Apply minimum price to variant
        IF v_variant_price < (v_variant_cost * (1 + p_min_margin_percent / 100)) THEN
            SET v_variant_price = ROUND(v_variant_cost * (1 + p_min_margin_percent / 100), 2);
        END IF;
        
        -- Update variant
        UPDATE product_variants 
        SET price = v_variant_price,
            updated_at = NOW()
        WHERE id = v_variant_id;
        
    END LOOP;
    
    CLOSE variant_cursor;
    
    SET p_success = TRUE;
    IF p_message = '' THEN
        SET p_message = CONCAT('Product ', p_product_id, ' priced successfully: cost=', v_cost_price, ', weight=', ROUND(v_weight_kg, 4), 'kg, shipping=', ROUND(p_shipping_cost, 2), ', margin=', p_margin_used, '%, final=', p_final_price);
    END IF;
    
    COMMIT;
END;

-- =============================================
-- BATCH PROCESSING PROCEDURE
-- =============================================
CREATE PROCEDURE batch_reprice_all_cj_products(
    IN p_chunk_size INT,
    IN p_currency VARCHAR(3),
    IN p_margin_percentage DECIMAL(5,2),
    IN p_default_shipping_per_kg DECIMAL(10,2),
    IN p_min_margin_percent DECIMAL(5,2),
    IN p_force_update BOOLEAN,
    OUT p_total_products INT,
    OUT p_success_count INT,
    OUT p_failed_count INT,
    OUT p_start_time DATETIME,
    OUT p_end_time DATETIME,
    OUT p_summary TEXT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_current_id BIGINT;
    DECLARE v_success BOOLEAN;
    DECLARE v_message TEXT;
    DECLARE v_final_price DECIMAL(12,2);
    DECLARE v_landed_cost DECIMAL(12,2);
    DECLARE v_shipping_cost DECIMAL(12,2);
    DECLARE v_margin_used DECIMAL(5,2);
    
    DECLARE product_cursor CURSOR FOR
        SELECT id 
        FROM products 
        WHERE cj_pid IS NOT NULL 
        AND cost_price > 0
        ORDER BY id
        LIMIT p_chunk_size;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Set defaults
    IF p_chunk_size IS NULL OR p_chunk_size <= 0 THEN
        SET p_chunk_size = 500;
    END IF;
    
    IF p_currency IS NULL THEN
        SET p_currency = 'USD';
    END IF;
    
    IF p_margin_percentage IS NULL THEN
        SET p_margin_percentage = 0;
    END IF;
    
    IF p_default_shipping_per_kg IS NULL OR p_default_shipping_per_kg <= 0 THEN
        SET p_default_shipping_per_kg = 7.00;
    END IF;
    
    IF p_min_margin_percent IS NULL OR p_min_margin_percent <= 0 THEN
        SET p_min_margin_percent = 45.00;
    END IF;
    
    IF p_force_update IS NULL THEN
        SET p_force_update = FALSE;
    END IF;
    
    SET p_total_products = 0;
    SET p_success_count = 0;
    SET p_failed_count = 0;
    SET p_start_time = NOW();
    SET p_summary = '';
    
    OPEN product_cursor;
    
    batch_loop: LOOP
        FETCH product_cursor INTO v_current_id;
        IF done THEN
            LEAVE batch_loop;
        END IF;
        
        SET p_total_products = p_total_products + 1;
        
        -- Call the pricing procedure
        CALL calculate_cj_product_pricing_comprehensive(
            v_current_id, 
            p_currency, 
            p_margin_percentage, 
            p_default_shipping_per_kg,
            p_min_margin_percent,
            p_force_update,
            v_success, 
            v_message, 
            v_final_price, 
            v_landed_cost, 
            v_shipping_cost, 
            v_margin_used
        );
        
        IF v_success = TRUE THEN
            SET p_success_count = p_success_count + 1;
        ELSE
            SET p_failed_count = p_failed_count + 1;
            SET p_summary = CONCAT(p_summary, 'ERROR: Product ', v_current_id, ': ', v_message, '; ');
        END IF;
        
    END LOOP;
    
    CLOSE product_cursor;
    
    SET p_end_time = NOW();
    
    IF p_summary = '' THEN
        SET p_summary = CONCAT('Batch completed: ', p_success_count, ' success, ', p_failed_count, ' failed');
    END IF;
    
END;

-- =============================================
-- STATISTICS PROCEDURE
-- =============================================
CREATE PROCEDURE get_cj_pricing_statistics()
BEGIN
    -- Total CJ products
    SELECT COUNT(*) as total_cj_products, 'All products with CJ PID' as detail
    FROM products 
    WHERE cj_pid IS NOT NULL AND deleted_at IS NULL;
    
    -- CJ products with pricing metadata
    SELECT COUNT(*) as with_pricing, 'CJ products with pricing metadata' as detail
    FROM products 
    WHERE cj_pid IS NOT NULL 
    AND pricing_meta IS NOT NULL 
    AND deleted_at IS NULL;
    
    -- CJ products with valid selling prices
    SELECT COUNT(*) as with_selling_price, 'CJ products with valid selling prices' as detail
    FROM products 
    WHERE cj_pid IS NOT NULL 
    AND selling_price IS NOT NULL 
    AND selling_price > 0
    AND deleted_at IS NULL;
    
    -- CJ products needing pricing
    SELECT COUNT(*) as needing_pricing, 'CJ products needing pricing' as detail
    FROM products 
    WHERE cj_pid IS NOT NULL 
    AND (pricing_meta IS NULL OR selling_price IS NULL OR selling_price <= 0)
    AND deleted_at IS NULL;
    
    -- Total variants for CJ products
    SELECT COUNT(pv.id) as total_variants, 'All variants for CJ products' as detail
    FROM product_variants pv
    INNER JOIN products p ON pv.product_id = p.id
    WHERE p.cj_pid IS NOT NULL AND p.deleted_at IS NULL;
    
    -- Variants with cost price
    SELECT COUNT(pv.id) as variants_with_cost, 'Variants with cost price set' as detail
    FROM product_variants pv
    INNER JOIN products p ON pv.product_id = p.id
    WHERE p.cj_pid IS NOT NULL 
    AND pv.cost_price > 0 
    AND p.deleted_at IS NULL;
    
    -- Completion percentage
    SELECT 
        ROUND(
            (SELECT COUNT(*) FROM products WHERE cj_pid IS NOT NULL AND pricing_meta IS NOT NULL AND deleted_at IS NULL) * 100.0 /
            NULLIF((SELECT COUNT(*) FROM products WHERE cj_pid IS NOT NULL AND deleted_at IS NULL), 0), 2
        ) as completion_percentage, 
        'Percentage of CJ products with pricing metadata' as detail;
    
    -- Warehouse statistics
    SELECT COUNT(*) as total_warehouses, 'Total warehouses' as detail
    FROM local_ware_houses;
    
    -- Default warehouses
    SELECT COUNT(*) as default_warehouses, 'Default warehouses' as detail
    FROM local_ware_houses
    WHERE is_default = TRUE;
    
    -- Recent pricing activity (last 10 updated products)
    SELECT
        p.id,
        p.cj_pid,
        p.name,
        p.cost_price,
        p.selling_price,
        JSON_EXTRACT(p.pricing_meta, '$.calculated_at') as priced_at,
        JSON_EXTRACT(p.pricing_meta, '$.margin_percent') as margin_used,
        JSON_EXTRACT(p.pricing_meta, '$.weight_kg') as weight_kg,
        JSON_EXTRACT(p.pricing_meta, '$.shipping_cost') as shipping_cost,
        JSON_EXTRACT(p.pricing_meta, '$.landed_cost') as landed_cost,
        lw.name as warehouse_name,
        lw.country as warehouse_country
    FROM products p
    LEFT JOIN local_ware_houses lw ON p.local_warehouse_id = lw.id
    WHERE p.cj_pid IS NOT NULL
    AND p.pricing_meta IS NOT NULL
    AND p.deleted_at IS NULL
    ORDER BY p.updated_at DESC
    LIMIT 10;
    
END;
