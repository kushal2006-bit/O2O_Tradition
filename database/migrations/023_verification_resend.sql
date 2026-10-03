-- Add a resend timestamp for customer email verification throttling.
SET @db_name = DATABASE();
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='customers' AND COLUMN_NAME='verification_last_sent_at');
SET @sql = IF(@exists=0,'ALTER TABLE customers ADD COLUMN verification_last_sent_at TIMESTAMP NULL AFTER verification_expires_at','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
