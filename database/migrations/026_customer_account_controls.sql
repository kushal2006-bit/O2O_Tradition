-- Customer account management and privacy controls.
SET @db_name = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND COLUMN_NAME='account_status');
SET @sql = IF(@exists=0,"ALTER TABLE customers ADD COLUMN account_status ENUM('active','deactivated') NOT NULL DEFAULT 'active' AFTER password",'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND COLUMN_NAME='deactivated_at');
SET @sql = IF(@exists=0,'ALTER TABLE customers ADD COLUMN deactivated_at TIMESTAMP NULL AFTER account_status','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND COLUMN_NAME='email_notifications_enabled');
SET @sql = IF(@exists=0,'ALTER TABLE customers ADD COLUMN email_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER deactivated_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
