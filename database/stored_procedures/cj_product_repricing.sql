-- CJ Product Repricing Stored Procedures
-- Created for Laravel Dropshipping Application
-- Based on actual database schema analysis
-- Compatible with MariaDB syntax

-- =============================================
-- PROCEDURE 1: calculate_cj_product_pricing
-- =============================================
    IN p_product_id BIGINT,
    IN p_currency VARCHAR(3),
    IN p_margin_percentage DECIMAL(5,2),
    OUT p_success BOOLEAN,
    OUT p_message TEXT
)
BEGIN
    DECLARE v_cost_price DECIMAL(12,2) DEFAULT 0;
    DECLARE v_weight_grams INT DEFAULT 0;
    DECLARE v_final_price DECIMAL(12,2);
    DECLARE v_min_price DECIMAL(12,2);
    DECLARE v_default_warehouse_id BIGINT;
    DECLARE v_has_variants INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
        p_message = MESSAGE_TEXT;
        SET p_success = FALSE;
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Get product cost and check if it exists
    SELECT COALESCE(cost_price, 0), 
           (SELECT COALESCE(SUM(weight_grams), 0) FROM product_variants WHERE product_id = p_product_id LIMIT 1)
    INTO v_cost_price, v_weight_grams
    FROM products 
    WHERE id = p_product_id AND cj_pid IS NOT NULL;
    
    IF v_cost_price = 0 THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Product ', p_product_id, ' not found or has no cost price');
        ROLLBACK;
        LEAVE;
    END IF;
    
    -- Find default warehouse (prioritize CN warehouse)
    SELECT id INTO v_default_warehouse_id
    FROM local_ware_houses 
    WHERE is_default = TRUE 
    ORDER BY CASE WHEN country = 'CN' THEN 1 ELSE 2 END, id
    LIMIT 1;
    
    -- Calculate pricing
    SET v_final_price = v_cost_price * (1 + p_margin_percentage / 100);
    SET v_min_price = v_cost_price * 1.10; -- 10% minimum markup
    
    -- Ensure minimum price
    IF v_final_price < v_min_price THEN
        SET v_final_price = v_min_price;
    END IF;
    
    -- Update product with pricing meta and warehouse
    UPDATE products 
    SET selling_price = v_final_price,
        local_warehouse_id = v_default_warehouse_id,
        pricing_meta = JSON_OBJECT(
            'currency', p_currency,
            'margin_percentage', p_margin_percentage,
            'cost_price', v_cost_price,
            'final_price', v_final_price,
            'min_price', v_min_price,
            'warehouse_id', v_default_warehouse_id,
            'calculated_at', NOW(),
            'weight_grams', v_weight_grams
        ),
        updated_at = NOW()
    WHERE id = p_product_id;
    
    -- Check if product has variants
    SELECT COUNT(*) INTO v_has_variants
    FROM product_variants 
    WHERE product_id = p_product_id;
    
    -- Call variant pricing procedure if variants exist
    IF v_has_variants > 0 THEN
        CALL calculate_cj_variant_pricing(p_product_id, p_currency, p_margin_percentage, p_success, p_message);
        IF p_success = FALSE THEN
            ROLLBACK;
            LEAVE;
        END IF;
    END IF;
    
    SET p_success = TRUE;
    SET p_message = CONCAT('Product ', p_product_id, ' priced successfully at ', v_final_price);
    
    COMMIT;
END //
DELIMITER ;

-- =============================================
-- PROCEDURE 2: calculate_cj_variant_pricing
-- =============================================
DELIMITER //
CREATE PROCEDURE calculate_cj_variant_pricing(
    IN p_product_id BIGINT,
    IN p_currency VARCHAR(3),
    IN p_margin_percentage DECIMAL(5,2),
    OUT p_success BOOLEAN,
    OUT p_message TEXT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_variant_id BIGINT;
    DECLARE v_cost_price DECIMAL(12,2);
    DECLARE v_final_price DECIMAL(12,2);
    DECLARE v_min_price DECIMAL(12,2);
    DECLARE v_weight_grams INT;
    DECLARE v_stock INT;
    DECLARE variant_cursor CURSOR FOR 
        SELECT id, COALESCE(cost_price, 0), COALESCE(weight_grams, 0), COALESCE(stock_on_hand, 0)
        FROM product_variants 
        WHERE product_id = p_product_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
        p_message = MESSAGE_TEXT;
        SET p_success = FALSE;
    END;
    
    SET p_success = TRUE;
    SET p_message = '';
    
    OPEN variant_cursor;
    
    variant_loop: LOOP
        FETCH variant_cursor INTO v_variant_id, v_cost_price, v_weight_grams, v_stock;
        IF done THEN
            LEAVE variant_loop;
        END IF;
        
        -- Skip variants with no cost price
        IF v_cost_price = 0 THEN
            SET p_message = CONCAT(p_message, 'Variant ', v_variant_id, ' has no cost price; ');
            ITERATE variant_loop;
        END IF;
        
        -- Calculate variant pricing
        SET v_final_price = v_cost_price * (1 + p_margin_percentage / 100);
        SET v_min_price = v_cost_price * 1.10;
        
        IF v_final_price < v_min_price THEN
            SET v_final_price = v_min_price;
        END IF;
        
        -- Update variant
        UPDATE product_variants 
        SET price = v_final_price,
            updated_at = NOW()
        WHERE id = v_variant_id;
        
        SET p_message = CONCAT(p_message, 'Variant ', v_variant_id, ' priced at ', v_final_price, '; ');
        
    END LOOP;
    
    CLOSE variant_cursor;
    
    IF p_message = '' THEN
        SET p_message = CONCAT('All variants for product ', p_product_id, ' priced successfully');
    END IF;
    
END //
DELIMITER ;

-- =============================================
-- PROCEDURE 3: batch_reprice_cj_products
-- =============================================
DELIMITER //
CREATE PROCEDURE batch_reprice_cj_products(
    IN p_chunk_size INT DEFAULT 1000,
    IN p_start_id BIGINT DEFAULT 0,
    IN p_end_id BIGINT DEFAULT 0,
    IN p_currency VARCHAR(3) DEFAULT 'USD',
    IN p_margin_percentage DECIMAL(5,2) DEFAULT 30.00,
    IN p_force_update BOOLEAN DEFAULT FALSE,
    OUT p_processed INT,
    OUT p_success INT,
    OUT p_failed INT,
    OUT p_start_time DATETIME,
    OUT p_end_time DATETIME,
    OUT p_message TEXT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_current_id BIGINT;
    DECLARE v_end_limit BIGINT;
    DECLARE v_success BOOLEAN;
    DECLARE v_error_message TEXT;
    DECLARE product_cursor CURSOR FOR 
        SELECT id 
        FROM products 
        WHERE cj_pid IS NOT NULL 
        AND id >= p_start_id 
        AND (p_end_id = 0 OR id <= p_end_id)
        AND (p_force_update = TRUE OR pricing_meta IS NULL)
        ORDER BY id
        LIMIT p_chunk_size;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Set end limit if not specified
    IF p_end_id = 0 THEN
        SELECT COALESCE(MAX(id), 0) INTO v_end_limit
        FROM products WHERE cj_pid IS NOT NULL;
    ELSE
        SET v_end_limit = p_end_id;
    END IF;
    
    SET p_processed = 0;
    SET p_success = 0;
    SET p_failed = 0;
    SET p_start_time = NOW();
    SET p_message = '';
    
    OPEN product_cursor;
    
    product_loop: LOOP
        FETCH product_cursor INTO v_current_id;
        IF done THEN
            LEAVE product_loop;
        END IF;
        
        SET p_processed = p_processed + 1;
        
        -- Calculate pricing for this product
        CALL calculate_cj_product_pricing(v_current_id, p_currency, p_margin_percentage, v_success, v_error_message);
        
        IF v_success = TRUE THEN
            SET p_success = p_success + 1;
        ELSE
            SET p_failed = p_failed + 1;
            SET p_message = CONCAT(p_message, 'Product ', v_current_id, ': ', v_error_message, '; ');
        END IF;
        
    END LOOP;
    
    CLOSE product_cursor;
    
    SET p_end_time = NOW();
    
    IF p_message = '' THEN
        SET p_message = CONCAT('Batch processing completed: ', p_success, ' success, ', p_failed, ' failed');
    END IF;
    
END //
DELIMITER ;

-- =============================================
-- PROCEDURE 4: get_cj_reprice_stats
-- =============================================
DELIMITER //
CREATE PROCEDURE get_cj_reprice_stats()
BEGIN
    -- Create temporary table for results
    DROP TEMPORARY TABLE IF EXISTS reprice_stats;
    CREATE TEMPORARY TABLE reprice_stats (
        stat_name VARCHAR(100),
        stat_value BIGINT,
        stat_detail TEXT
    );
    
    -- Total CJ products
    INSERT INTO reprice_stats (stat_name, stat_value, stat_detail)
    SELECT 'total_cj_products', COUNT(*), 'All products with CJ PID'
    FROM products 
    WHERE cj_pid IS NOT NULL AND deleted_at IS NULL;
    
    -- CJ products with pricing_meta
    INSERT INTO reprice_stats (stat_name, stat_value, stat_detail)
    SELECT 'cj_products_with_pricing', COUNT(*), 'CJ products with pricing metadata'
    FROM products 
    WHERE cj_pid IS NOT NULL 
    AND pricing_meta IS NOT NULL 
    AND deleted_at IS NULL;
    
    -- CJ products without pricing_meta
    INSERT INTO reprice_stats (stat_name, stat_value, stat_detail)
    SELECT 'cj_products_without_pricing', COUNT(*), 'CJ products needing pricing'
    FROM products 
    WHERE cj_pid IS NOT NULL 
    AND (pricing_meta IS NULL OR pricing_meta = '{}')
    AND deleted_at IS NULL;
    
    -- Total variants for CJ products
    INSERT INTO reprice_stats (stat_name, stat_value, stat_detail)
    SELECT 'total_cj_variants', COUNT(pv.id), 'All variants for CJ products'
    FROM product_variants pv
    INNER JOIN products p ON pv.product_id = p.id
    WHERE p.cj_pid IS NOT NULL AND p.deleted_at IS NULL;
    
    -- Variants with cost price
    INSERT INTO reprice_stats (stat_name, stat_value, stat_detail)
    SELECT 'cj_variants_with_cost', COUNT(pv.id), 'Variants with cost price set'
    FROM product_variants pv
    INNER JOIN products p ON pv.product_id = p.id
    WHERE p.cj_pid IS NOT NULL 
    AND pv.cost_price > 0 
    AND p.deleted_at IS NULL;
    
    -- Completion percentage
    INSERT INTO reprice_stats (stat_name, stat_value, stat_detail)
    SELECT 
        'pricing_completion_percentage', 
        ROUND(
            (SELECT COUNT(*) FROM products WHERE cj_pid IS NOT NULL AND pricing_meta IS NOT NULL AND deleted_at IS NULL) * 100.0 / 
            NULLIF((SELECT COUNT(*) FROM products WHERE cj_pid IS NOT NULL AND deleted_at IS NULL), 0), 2
        ),
        'Percentage of CJ products with pricing metadata';
    
    -- Default warehouse info
    INSERT INTO reprice_stats (stat_name, stat_value, stat_detail)
    SELECT 
        'default_warehouse_count', 
        COUNT(*), 
        CONCAT('Default warehouses: ', GROUP_CONCAT(CONCAT(name, ' (', country, ')') SEPARATOR ', '))
    FROM local_ware_houses 
    WHERE is_default = TRUE;
    
    -- Recent activity (last 10 updated products)
    SELECT 
        p.id,
        p.cj_pid,
        p.name,
        p.cost_price,
        p.selling_price,
        p.updated_at,
        JSON_EXTRACT(p.pricing_meta, '$.calculated_at') as pricing_calculated_at,
        lw.name as warehouse_name,
        lw.country as warehouse_country
    FROM products p
    LEFT JOIN local_ware_houses lw ON p.local_warehouse_id = lw.id
    WHERE p.cj_pid IS NOT NULL 
    AND p.pricing_meta IS NOT NULL
    AND p.deleted_at IS NULL
    ORDER BY p.updated_at DESC
    LIMIT 10;
    
    -- Return stats
    SELECT * FROM reprice_stats ORDER BY stat_name;
    
    DROP TEMPORARY TABLE reprice_stats;
    
END //
DELIMITER ;
