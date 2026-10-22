# -- Single CJ Product Pricing Procedure - Weight-Based
# -- MariaDB compatible - NO cj_shipping_amount dependency
#
# DROP PROCEDURE IF EXISTS update_cj_product_price_weight_based;
#
# DELIMITER $$
#
# CREATE PROCEDURE update_cj_product_price_weight_based(
#     IN p_product_id BIGINT,
#     IN p_margin_override DECIMAL(5,2),
#     IN p_default_shipping_rate DECIMAL(10,2),
#     IN p_min_margin_percent DECIMAL(5,2),
#     IN p_force_update BOOLEAN,
#     OUT p_success BOOLEAN,
#     OUT p_message TEXT
# )
# proc_body: BEGIN
#     -- Product data
#     DECLARE v_cost_price DECIMAL(12,2);
#     DECLARE v_weight_grams INT;
#     DECLARE v_weight_kg DECIMAL(10,4);
#     DECLARE v_cj_shipping DECIMAL(12,2);
#     DECLARE v_warehouse_id BIGINT;
#     DECLARE v_warehouse_rate DECIMAL(10,2);
#     DECLARE v_product_exists INT;
#
#     -- Pricing calculations
#     DECLARE v_shipping_cost DECIMAL(12,2);
#     DECLARE v_landed_cost DECIMAL(12,2);
#     DECLARE v_weight_margin DECIMAL(5,2);
#     DECLARE v_final_margin DECIMAL(5,2);
#     DECLARE v_final_price DECIMAL(12,2);
#     DECLARE v_min_price DECIMAL(12,2);
#     DECLARE v_already_priced INT;
#
#     -- Error handler
#     DECLARE EXIT HANDLER FOR SQLEXCEPTION
#         BEGIN
#             GET DIAGNOSTICS CONDITION 1 p_message = MESSAGE_TEXT;
#             SET p_success = FALSE;
#         END;
#
#     -- Initialize
#     SET v_cost_price = 0;
#     SET v_weight_grams = 0;
#     SET v_weight_kg = 0;
#     SET v_cj_shipping = 0;  -- ✅ No CJ shipping column, default to 0
#     SET v_warehouse_id = 0;
#     SET v_warehouse_rate = p_default_shipping_rate;
#     SET v_product_exists = 0;
#     SET p_success = FALSE;
#     SET p_message = '';
#
#     -- Set defaults
#     IF p_default_shipping_rate IS NULL OR p_default_shipping_rate <= 0 THEN
#         SET p_default_shipping_rate = 14.00;
#     END IF;
#
#     IF p_min_margin_percent IS NULL OR p_min_margin_percent <= 0 THEN
#         SET p_min_margin_percent = 45.00;
#     END IF;
#
#     IF p_force_update IS NULL THEN
#         SET p_force_update = FALSE;
#     END IF;
#
#     -- Get product data (REMOVED cj_shipping_amount)
#     SELECT
#         COALESCE(cost_price, 0),
#         COALESCE(local_warehouse_id, 0),
#         CASE WHEN cost_price > 0 THEN 1 ELSE 0 END
#     INTO v_cost_price, v_warehouse_id, v_product_exists
#     FROM products
#     WHERE id = p_product_id;
#
#     -- Validate product
#     IF v_product_exists = 0 THEN
#         SET p_message = CONCAT('Product ', p_product_id, ' not found or has no cost price');
#         LEAVE proc_body;
#     END IF;
#
#     -- Check if already priced (unless forced)
#     IF p_force_update = FALSE THEN
#         SELECT COUNT(*) INTO v_already_priced
#         FROM products
#         WHERE id = p_product_id
#           AND selling_price IS NOT NULL
#           AND selling_price > 0
#           AND pricing_meta IS NOT NULL;
#
#         IF v_already_priced > 0 THEN
#             SET p_success = TRUE;
#             SET p_message = 'Product already has valid pricing';
#             LEAVE proc_body;
#         END IF;
#     END IF;
#
#     -- Get warehouse (default to first available)
#     IF v_warehouse_id = 0 THEN
#         SELECT id INTO v_warehouse_id
#         FROM local_ware_houses
#         WHERE is_default = TRUE
#         LIMIT 1;
#
#         IF v_warehouse_id IS NULL THEN
#             SELECT id INTO v_warehouse_id
#             FROM local_ware_houses
#             LIMIT 1;
#         END IF;
#     END IF;
#
#     -- Get warehouse shipping rate
#     IF v_warehouse_id IS NOT NULL THEN
#         SELECT COALESCE(shipping_cost_per_kg, p_default_shipping_rate)
#         INTO v_warehouse_rate
#         FROM local_ware_houses
#         WHERE id = v_warehouse_id;
#     END IF;
#
#     -- Get product weight
#     SELECT COALESCE(weight_grams, 0) INTO v_weight_grams
#     FROM products
#     WHERE id = p_product_id;
#
#     -- If no product weight, get lightest variant
#     IF v_weight_grams = 0 THEN
#         SELECT COALESCE(MIN(weight_grams), 0) INTO v_weight_grams
#         FROM product_variants
#         WHERE product_id = p_product_id AND weight_grams > 0;
#     END IF;
#
#     -- Convert to kg
#     SET v_weight_kg = v_weight_grams / 1000.0;
#
#     -- Calculate weight-based margin
#     SET v_weight_margin = CASE
#                               WHEN v_weight_kg <= 0.1 THEN 50.0
#                               WHEN v_weight_kg <= 0.5 THEN 40.0
#                               WHEN v_weight_kg <= 1.0 THEN 35.0
#                               WHEN v_weight_kg <= 2.0 THEN 30.0
#                               ELSE 25.0
#         END;
#
#     -- Use override margin or weight-based margin
#     IF p_margin_override IS NOT NULL AND p_margin_override > 0 THEN
#         SET v_final_margin = p_margin_override;
#     ELSE
#         SET v_final_margin = v_weight_margin;
#     END IF;
#
#     -- Calculate shipping cost (NO CJ shipping, only warehouse rate)
#     SET v_shipping_cost = (v_weight_kg * v_warehouse_rate);
#
#     -- Calculate landed cost
#     SET v_landed_cost = v_cost_price + v_shipping_cost;
#
#     -- Calculate final price with margin
#     SET v_final_price = v_landed_cost * (1 + v_final_margin / 100);
#
#     -- Apply minimum margin
#     SET v_min_price = v_landed_cost * (1 + p_min_margin_percent / 100);
#     IF v_final_price < v_min_price THEN
#         SET v_final_price = v_min_price;
#     END IF;
#
#     -- Corruption protection (15x max markup)
#     IF v_final_price > (v_cost_price * 15.0) THEN
#         SET v_final_price = v_min_price;
#         SET p_message = CONCAT('Price corruption detected, forced to minimum: ', v_final_price);
#     END IF;
#
#     -- Update product
#     UPDATE products
#     SET
#         selling_price = ROUND(v_final_price, 2),
#         local_warehouse_id = v_warehouse_id,
#         pricing_meta = JSON_OBJECT(
#             'currency', 'USD',
#             'margin_percent', v_final_margin,
#             'margin_source', CASE WHEN p_margin_override IS NOT NULL THEN 'manual' ELSE 'weight_based' END,
#             'cost_price', v_cost_price,
#             'weight_kg', ROUND(v_weight_kg, 4),
#             'weight_grams', v_weight_grams,
#             'shipping_cost', ROUND(v_shipping_cost, 2),
#             'landed_cost', ROUND(v_landed_cost, 2),
#             'final_price', ROUND(v_final_price, 2),
#             'warehouse_id', v_warehouse_id,
#             'calculated_at', NOW()
#                        ),
#         updated_at = NOW()
#     WHERE id = p_product_id;
#
#     -- Update variants
#     UPDATE product_variants
#     SET price = ROUND(cost_price * (1 + v_final_margin / 100), 2),
#         updated_at = NOW()
#     WHERE product_id = p_product_id
#       AND cost_price > 0;
#
#     -- Apply minimum price to variants
#     UPDATE product_variants
#     SET price = GREATEST(
#         price,
#         ROUND(cost_price * (1 + p_min_margin_percent / 100), 2)
#                 )
#     WHERE product_id = p_product_id
#       AND cost_price > 0;
#
#     SET p_success = TRUE;
#     IF p_message = '' THEN
#         SET p_message = CONCAT(
#             'Product ', p_product_id, ' priced: cost=', v_cost_price,
#             ', weight=', ROUND(v_weight_kg, 4), 'kg, margin=', v_final_margin,
#             '%, price=', ROUND(v_final_price, 2)
#                         );
#     END IF;
#
# END proc_body$$
#
# DELIMITER ;
CALL update_cj_product_price_weight_based(
    123, NULL, 7.00, 45.00, FALSE, @success, @message
     );

SELECT @success, @message;
