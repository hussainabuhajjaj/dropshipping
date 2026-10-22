-- VARIANT-LEVEL SMART PRICING PROCEDURE - DATABASE COMPATIBLE VERSION
-- All columns verified against actual database schema

DROP PROCEDURE IF EXISTS update_all_products_variants_smart;
DROP TABLE IF EXISTS pricing_batch_log;

DELIMITER $$

-- Create log table
CREATE TABLE IF NOT EXISTS pricing_batch_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50),
    product_id BIGINT,
    variant_id BIGINT,
    status ENUM('success', 'skipped', 'error'),
    old_price DECIMAL(12,2),
    new_price DECIMAL(12,2),
    margin_percent DECIMAL(5,2),
    weight_kg DECIMAL(10,4),
    weight_grams INT,
    cost_price DECIMAL(12,2),
    shipping_cost DECIMAL(12,2),
    landed_cost DECIMAL(12,2),
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (batch_id),
    INDEX idx_product (product_id),
    INDEX idx_variant (variant_id),
    INDEX idx_status (status)
);

