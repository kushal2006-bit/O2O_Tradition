-- Authentication hardening for vendor and admin accounts.
SET @db_name = DATABASE();

SET @vendor_failed_exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='failed_login_count');
SET @sql=IF(@vendor_failed_exists=0,'ALTER TABLE vendors ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0 AFTER password','SELECT 1');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;

SET @vendor_locked_exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='vendors' AND COLUMN_NAME='locked_until');
SET @sql=IF(@vendor_locked_exists=0,'ALTER TABLE vendors ADD COLUMN locked_until TIMESTAMP NULL AFTER failed_login_count','SELECT 1');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;

SET @admin_failed_exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='admins' AND COLUMN_NAME='failed_login_count');
SET @sql=IF(@admin_failed_exists=0,'ALTER TABLE admins ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0 AFTER password','SELECT 1');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;

SET @admin_locked_exists=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='admins' AND COLUMN_NAME='locked_until');
SET @sql=IF(@admin_locked_exists=0,'ALTER TABLE admins ADD COLUMN locked_until TIMESTAMP NULL AFTER failed_login_count','SELECT 1');
PREPARE stmt FROM @sql;EXECUTE stmt;DEALLOCATE PREPARE stmt;
