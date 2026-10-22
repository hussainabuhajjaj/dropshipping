-- Check procedure exists
SHOW PROCEDURE STATUS WHERE Db = DATABASE();

-- Check table exists
SHOW TABLES LIKE 'pricing_batch_log';

-- Run and see results
CALL update_all_products_variants_smart(14.00, 45.00, FALSE, NULL, @t, @s, @k, @e, @m);
SELECT @m;

-- Check logs
SELECT status, COUNT(*) FROM pricing_batch_log GROUP BY status;

update product_variants set price = 0 where price  is not null;
