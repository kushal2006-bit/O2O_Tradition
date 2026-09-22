-- Safe migration for vendor attribution on trust records.
SET @has_condition_vendor := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='condition_reports' AND COLUMN_NAME='created_by_vendor_id');
SET @sql := IF(@has_condition_vendor=0,
  'ALTER TABLE condition_reports ADD COLUMN created_by_vendor_id INT NULL AFTER rental_order_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_condition_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='condition_reports' AND CONSTRAINT_NAME='fk_condition_reports_vendor');
SET @sql := IF(@has_condition_fk=0,
  'ALTER TABLE condition_reports ADD CONSTRAINT fk_condition_reports_vendor FOREIGN KEY (created_by_vendor_id) REFERENCES vendors(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sanitize_vendor := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sanitization_records' AND COLUMN_NAME='created_by_vendor_id');
SET @sql := IF(@has_sanitize_vendor=0,
  'ALTER TABLE sanitization_records ADD COLUMN created_by_vendor_id INT NULL AFTER rental_order_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_sanitize_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='sanitization_records' AND CONSTRAINT_NAME='fk_sanitization_records_vendor');
SET @sql := IF(@has_sanitize_fk=0,
  'ALTER TABLE sanitization_records ADD CONSTRAINT fk_sanitization_records_vendor FOREIGN KEY (created_by_vendor_id) REFERENCES vendors(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
