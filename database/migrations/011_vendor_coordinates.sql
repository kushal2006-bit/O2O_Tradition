-- Hyperlocal vendor coordinates for map discovery.
SET @db_name = DATABASE();

SET @vendor_lat_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='latitude'
);
SET @sql = IF(@vendor_lat_exists=0,
  'ALTER TABLE vendors ADD COLUMN latitude DECIMAL(10,7) NULL AFTER address',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @vendor_lng_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='longitude'
);
SET @sql = IF(@vendor_lng_exists=0,
  'ALTER TABLE vendors ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE INDEX IF NOT EXISTS idx_vendors_pincode ON vendors (pincode);
