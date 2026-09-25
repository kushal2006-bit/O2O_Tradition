-- Saved-item availability and price alert preferences.
SET @db_name = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='wishlists' AND COLUMN_NAME='availability_alert');
SET @sql = IF(@exists=0,'ALTER TABLE wishlists ADD COLUMN availability_alert TINYINT(1) NOT NULL DEFAULT 1 AFTER item_id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='wishlists' AND COLUMN_NAME='price_alert');
SET @sql = IF(@exists=0,'ALTER TABLE wishlists ADD COLUMN price_alert TINYINT(1) NOT NULL DEFAULT 1 AFTER availability_alert','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='wishlists' AND COLUMN_NAME='last_notified_price');
SET @sql = IF(@exists=0,'ALTER TABLE wishlists ADD COLUMN last_notified_price DECIMAL(10,2) NULL AFTER price_alert','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE wishlists w JOIN items i ON i.id=w.item_id SET w.last_notified_price=COALESCE(w.last_notified_price,i.rent_per_day) WHERE w.last_notified_price IS NULL;
