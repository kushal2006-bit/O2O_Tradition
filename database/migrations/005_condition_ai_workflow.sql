-- Safe migration for vendor-owned AI condition reports.
SET @has_vendor := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='condition_ai_reports' AND COLUMN_NAME='vendor_id');
SET @sql := IF(@has_vendor=0,
  'ALTER TABLE condition_ai_reports ADD COLUMN vendor_id INT NULL AFTER item_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_status := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='condition_ai_reports' AND COLUMN_NAME='status');
SET @sql := IF(@has_status=0,
  'ALTER TABLE condition_ai_reports ADD COLUMN status ENUM(''queued'',''processing'',''completed'',''failed'') DEFAULT ''queued'' AFTER confidence',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_notes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='condition_ai_reports' AND COLUMN_NAME='notes');
SET @sql := IF(@has_notes=0,
  'ALTER TABLE condition_ai_reports ADD COLUMN notes TEXT NULL AFTER status',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='condition_ai_reports' AND CONSTRAINT_NAME='fk_condition_ai_vendor');
SET @sql := IF(@has_fk=0,
  'ALTER TABLE condition_ai_reports ADD CONSTRAINT fk_condition_ai_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
