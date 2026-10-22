-- Batch Processing Examples for CJ Product Repricing
-- Multiple ways to price all products

-- =============================================
-- METHOD 1: Using the existing batch procedure
-- =============================================

-- Process all unpriced products in chunks of 1000
CALL batch_reprice_cj_products(
    1000,      -- chunk_size
    0,          -- start_id (0 = beginning)
    0,          -- end_id (0 = auto-detect)
    'USD',      -- currency
    30.0,       -- margin_percentage
    FALSE,      -- force_update (FALSE = only unpriced)
    @processed, @success, @failed, @start, @end, @message
);

-- Check results
SELECT 
    @processed as total_processed,
    @success as successful,
    @failed as failed,
    @start as start_time,
    @end as end_time,
    @message as status_message;

-- =============================================
-- METHOD 2: Using the advanced weight-based procedure
-- =============================================

-- Create a simple batch runner for the advanced procedure
DELIMITER //
CREATE PROCEDURE batch_reprice_all_products_advanced(
    IN p_chunk_size INT DEFAULT 500,
    IN p_default_shipping_per_kg DECIMAL(10,2) DEFAULT 7.00,
    IN p_margin_override DECIMAL(5,2) DEFAULT NULL, -- NULL = use weight-based
    OUT p_total_processed INT,
    OUT p_total_success INT,
    OUT p_total_failed INT
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
    
    SET p_total_processed = 0;
    SET p_total_success = 0;
    SET p_total_failed = 0;
    
    OPEN product_cursor;
    
    batch_loop: LOOP
        FETCH product_cursor INTO v_current_id;
        IF done THEN
            LEAVE batch_loop;
        END IF;
        
        SET p_total_processed = p_total_processed + 1;
        
        -- Call the advanced pricing procedure
        CALL calculate_cj_product_pricing_advanced(
            v_current_id, 
            'USD', 
            p_margin_override, 
            p_default_shipping_per_kg,
            FALSE,
            v_success, 
            v_message, 
            v_final_price, 
            v_landed_cost, 
            v_shipping_cost, 
            v_margin_used
        );
        
        IF v_success = TRUE THEN
            SET p_total_success = p_total_success + 1;
        ELSE
            SET p_total_failed = p_total_failed + 1;
            -- Log the error (you could insert into a log table here)
            SELECT CONCAT('ERROR: Product ', v_current_id, ': ', v_message) as error_log;
        END IF;
        
    END LOOP;
    
    CLOSE product_cursor;
    
END //
DELIMITER ;

-- =============================================
-- METHOD 3: One-liner for all products (simple approach)
-- =============================================

-- Direct SQL approach - Update all products with basic pricing
UPDATE products p
SET 
    selling_price = GREATEST(
        cost_price * 1.10,  -- Minimum 10% markup
        cost_price * (1 + 30.0 / 100)  -- 30% margin
    ),
    pricing_meta = JSON_OBJECT(
        'currency', 'USD',
        'margin_percent', 30.0,
        'cost_price', cost_price,
        'final_price', GREATEST(cost_price * 1.10, cost_price * 1.30),
        'warehouse_id', (SELECT id FROM local_ware_houses WHERE is_default = TRUE LIMIT 1),
        'calculated_at', NOW()
    ),
    updated_at = NOW()
WHERE p.cj_pid IS NOT NULL 
AND p.cost_price > 0;

-- =============================================
-- METHOD 4: Application-style batch processing
-- =============================================

-- Create a job queue table for tracking
CREATE TABLE IF NOT EXISTS pricing_job_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_product_id (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Queue all products for processing
INSERT INTO pricing_job_queue (product_id)
SELECT id 
FROM products 
WHERE cj_pid IS NOT NULL 
AND cost_price > 0
AND id NOT IN (SELECT product_id FROM pricing_job_queue WHERE status IN ('pending', 'processing'));

-- Process next batch from queue
DELIMITER //
CREATE PROCEDURE process_pricing_queue_batch(
    IN p_batch_size INT DEFAULT 100,
    OUT p_processed INT,
    OUT p_success INT,
    OUT p_failed INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_job_id BIGINT;
    DECLARE v_product_id BIGINT;
    DECLARE v_success BOOLEAN;
    DECLARE v_message TEXT;
    DECLARE v_final_price DECIMAL(12,2);
    DECLARE v_landed_cost DECIMAL(12,2);
    DECLARE v_shipping_cost DECIMAL(12,2);
    DECLARE v_margin_used DECIMAL(5,2);
    
    DECLARE job_cursor CURSOR FOR 
        SELECT id, product_id 
        FROM pricing_job_queue 
        WHERE status = 'pending' 
        AND attempts < max_attempts
        ORDER BY created_at
        LIMIT p_batch_size;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    SET p_processed = 0;
    SET p_success = 0;
    SET p_failed = 0;
    
    OPEN job_cursor;
    
    job_loop: LOOP
        FETCH job_cursor INTO v_job_id, v_product_id;
        IF done THEN
            LEAVE job_loop;
        END IF;
        
        SET p_processed = p_processed + 1;
        
        -- Mark as processing
        UPDATE pricing_job_queue 
        SET status = 'processing', started_at = NOW(), attempts = attempts + 1
        WHERE id = v_job_id;
        
        -- Call the advanced pricing procedure
        CALL calculate_cj_product_pricing_advanced(
            v_product_id, 
            'USD', 
            NULL,  -- Use weight-based margins
            7.00,  -- Default shipping rate
            FALSE,
            v_success, 
            v_message, 
            v_final_price, 
            v_landed_cost, 
            v_shipping_cost, 
            v_margin_used
        );
        
        -- Update job status
        IF v_success = TRUE THEN
            UPDATE pricing_job_queue 
            SET status = 'completed', completed_at = NOW()
            WHERE id = v_job_id;
            SET p_success = p_success + 1;
        ELSE
            UPDATE pricing_job_queue 
            SET status = 'failed', error_message = v_message, completed_at = NOW()
            WHERE id = v_job_id;
            SET p_failed = p_failed + 1;
        END IF;
        
    END LOOP;
    
    CLOSE job_cursor;
    
END //
DELIMITER ;

-- =============================================
-- USAGE EXAMPLES
-- =============================================

-- Example 1: Process all unpriced products (basic method)
/*
CALL batch_reprice_cj_products(1000, 0, 0, 'USD', 30.0, FALSE, @p, @s, @f, @st, @et, @msg);
SELECT @p as processed, @s as success, @f as failed, @msg as message;
*/

-- Example 2: Process all products with weight-based pricing (advanced method)
/*
CALL batch_reprice_all_products_advanced(500, 7.00, NULL, @total, @success, @failed);
SELECT @total as total_processed, @success as successful, @failed as failed;
*/

-- Example 3: Force reprice all products (override with 25% margin)
/*
CALL batch_reprice_all_products_advanced(500, 7.00, 25.0, @total, @success, @failed);
SELECT @total as total_processed, @success as successful, @failed as failed;
*/

-- Example 4: Quick one-liner update (no error handling)
/*
-- Run this direct UPDATE to price all products at once
UPDATE products p
SET selling_price = GREATEST(cost_price * 1.10, cost_price * 1.30),
    pricing_meta = JSON_OBJECT('currency', 'USD', 'margin_percent', 30.0, 'calculated_at', NOW()),
    updated_at = NOW()
WHERE p.cj_pid IS NOT NULL AND p.cost_price > 0;
*/

-- Example 5: Queue-based processing (most robust)
/*
-- Queue all products
INSERT INTO pricing_job_queue (product_id)
SELECT id FROM products WHERE cj_pid IS NOT NULL AND cost_price > 0;

-- Process in batches
CALL process_pricing_queue_batch(100, @processed, @success, @failed);
SELECT @processed as processed, @success as success, @failed as failed;

-- Check queue status
SELECT status, COUNT(*) as count 
FROM pricing_job_queue 
GROUP BY status;
*/

-- =============================================
-- MONITORING QUERIES
-- =============================================

-- Check pricing progress
SELECT 
    COUNT(*) as total_cj_products,
    COUNT(CASE WHEN pricing_meta IS NOT NULL THEN 1 END) as with_pricing,
    COUNT(CASE WHEN pricing_meta IS NULL THEN 1 END) as without_pricing,
    ROUND(COUNT(CASE WHEN pricing_meta IS NOT NULL THEN 1 END) * 100.0 / COUNT(*), 2) as completion_percentage
FROM products 
WHERE cj_pid IS NOT NULL AND deleted_at IS NULL;

-- Recent pricing activity
SELECT 
    id, cj_pid, name, cost_price, selling_price,
    JSON_EXTRACT(pricing_meta, '$.calculated_at') as priced_at,
    JSON_EXTRACT(pricing_meta, '$.margin_percent') as margin_used
FROM products 
WHERE cj_pid IS NOT NULL 
AND pricing_meta IS NOT NULL
ORDER BY JSON_EXTRACT(pricing_meta, '$.calculated_at') DESC
LIMIT 10;

-- Products needing pricing
SELECT 
    id, cj_pid, name, cost_price,
    CASE 
        WHEN cost_price IS NULL OR cost_price <= 0 THEN 'NO COST'
        WHEN selling_price IS NULL OR selling_price <= 0 THEN 'NO SELLING PRICE'
        WHEN pricing_meta IS NULL THEN 'NO PRICING META'
        ELSE 'UNKNOWN'
    END as issue
FROM products 
WHERE cj_pid IS NOT NULL 
AND (pricing_meta IS NULL OR selling_price IS NULL OR selling_price <= 0 OR cost_price IS NULL OR cost_price <= 0)
ORDER BY id
LIMIT 20;
