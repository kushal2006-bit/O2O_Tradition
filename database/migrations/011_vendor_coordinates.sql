-- Hyperlocal vendor coordinates for map discovery.
SET @db_name = DATABASE();
SET @vendor_lat_exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='latitude');
SET @sql=IF(@vendor_lat_exists=0,'ALTER TABLE vendors ADD COLUMN latitude DECIMAL(10,7) NULL AFTER address','SELECT 1');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @vendor_lng_exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='longitude');
SET @sql=IF(@vendor_lng_exists=0,'ALTER TABLE vendors ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude','SELECT 1');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
SET @vendor_pincode_index_exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND INDEX_NAME='idx_vendors_pincode');
SET @sql=IF(@vendor_pincode_index_exists=0,'CREATE INDEX idx_vendors_pincode ON vendors (pincode)','SELECT 1');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
