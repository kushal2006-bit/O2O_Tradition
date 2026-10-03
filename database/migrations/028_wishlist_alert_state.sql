-- Track last observed wishlist availability for transition alerts.
SET @db_name = DATABASE();
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='wishlists' AND COLUMN_NAME='last_notified_available');
SET @sql = IF(@exists=0,'ALTER TABLE wishlists ADD COLUMN last_notified_available TINYINT(1) NULL AFTER last_notified_price','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE wishlists w JOIN items i ON i.id=w.item_id SET w.last_notified_available=i.available WHERE w.last_notified_available IS NULL;
