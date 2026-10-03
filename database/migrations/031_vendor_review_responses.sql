SET @db_name = DATABASE();
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='reviews' AND COLUMN_NAME='vendor_response');
SET @sql = IF(@exists=0,'ALTER TABLE reviews ADD COLUMN vendor_response TEXT NULL AFTER review','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='reviews' AND COLUMN_NAME='vendor_responded_at');
SET @sql = IF(@exists=0,'ALTER TABLE reviews ADD COLUMN vendor_responded_at TIMESTAMP NULL AFTER vendor_response','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
