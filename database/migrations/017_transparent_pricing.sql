-- Transparent rental pricing: keep base rent separate from refundable deposit,
-- delivery and the final amount recorded at checkout.
SET @db_name = DATABASE();

SET @security_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='security_deposit'
);
SET @sql = IF(@security_exists=0,
  'ALTER TABLE orders ADD COLUMN security_deposit DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_rent',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @delivery_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='delivery_charge'
);
SET @sql = IF(@delivery_exists=0,
  'ALTER TABLE orders ADD COLUMN delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER security_deposit',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @final_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='final_total'
);
SET @sql = IF(@final_exists=0,
  'ALTER TABLE orders ADD COLUMN final_total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER delivery_charge',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE orders
SET security_deposit=COALESCE(security_deposit,0),
    delivery_charge=COALESCE(delivery_charge,0),
    final_total=CASE WHEN final_total=0 THEN GREATEST(0,COALESCE(total_rent,0)+COALESCE(reward_credit_used,0)) ELSE final_total END;
