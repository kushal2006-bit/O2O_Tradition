-- Preserve the size selected during rental and purchase checkout.
SET @db_name = DATABASE();

SET @orders_size_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='product_size_id'
);
SET @sql = IF(@orders_size_exists=0,
  'ALTER TABLE orders ADD COLUMN product_size_id INT NULL AFTER item_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orders_fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND CONSTRAINT_NAME='fk_orders_product_size'
);
SET @sql = IF(@orders_fk_exists=0,
  'ALTER TABLE orders ADD CONSTRAINT fk_orders_product_size FOREIGN KEY (product_size_id) REFERENCES product_sizes(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @purchase_size_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='purchase_order_items' AND COLUMN_NAME='product_size_id'
);
SET @sql = IF(@purchase_size_exists=0,
  'ALTER TABLE purchase_order_items ADD COLUMN product_size_id INT NULL AFTER item_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @purchase_fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='purchase_order_items' AND CONSTRAINT_NAME='fk_purchase_order_items_product_size'
);
SET @sql = IF(@purchase_fk_exists=0,
  'ALTER TABLE purchase_order_items ADD CONSTRAINT fk_purchase_order_items_product_size FOREIGN KEY (product_size_id) REFERENCES product_sizes(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
