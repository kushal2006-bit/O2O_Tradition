-- Reconcile an existing O2O Tradition database safely.
SET @has_col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seller_listings' AND COLUMN_NAME = 'image_path'
);
SET @sql := IF(@has_col=0,
  'ALTER TABLE seller_listings ADD COLUMN image_path VARCHAR(255) NULL AFTER pickup_option',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
