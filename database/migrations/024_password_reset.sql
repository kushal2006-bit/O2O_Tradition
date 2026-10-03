-- Secure customer password reset tokens.
SET @db_name = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND COLUMN_NAME='password_reset_token_hash');
SET @sql = IF(@exists=0,'ALTER TABLE customers ADD COLUMN password_reset_token_hash CHAR(64) NULL AFTER verification_last_sent_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND COLUMN_NAME='password_reset_expires_at');
SET @sql = IF(@exists=0,'ALTER TABLE customers ADD COLUMN password_reset_expires_at TIMESTAMP NULL AFTER password_reset_token_hash','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND COLUMN_NAME='password_reset_last_sent_at');
SET @sql = IF(@exists=0,'ALTER TABLE customers ADD COLUMN password_reset_last_sent_at TIMESTAMP NULL AFTER password_reset_expires_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND INDEX_NAME='uq_customers_password_reset_token');
SET @sql = IF(@idx_exists=0,'ALTER TABLE customers ADD UNIQUE KEY uq_customers_password_reset_token (password_reset_token_hash)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
