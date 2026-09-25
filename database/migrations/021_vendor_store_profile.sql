SET @db_name = DATABASE();

SET @opening_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='opening_time');
SET @sql = IF(@opening_exists=0,'ALTER TABLE vendors ADD COLUMN opening_time TIME NULL AFTER address','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @closing_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='closing_time');
SET @sql = IF(@closing_exists=0,'ALTER TABLE vendors ADD COLUMN closing_time TIME NULL AFTER opening_time','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @pickup_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='pickup_instructions');
SET @sql = IF(@pickup_exists=0,'ALTER TABLE vendors ADD COLUMN pickup_instructions VARCHAR(500) NULL AFTER closing_time','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @delivery_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='delivery_available');
SET @sql = IF(@delivery_exists=0,'ALTER TABLE vendors ADD COLUMN delivery_available TINYINT(1) NOT NULL DEFAULT 0 AFTER pickup_instructions','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;