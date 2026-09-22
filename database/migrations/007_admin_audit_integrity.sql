-- O2O Tradition: schema integrity hardening for admin audit records.
-- Safe to run on an existing installation.

SET @db := DATABASE();

SET @has_fk := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@db
    AND TABLE_NAME='admin_actions'
    AND CONSTRAINT_NAME='fk_admin_actions_admin'
);

SET @sql := IF(
  @has_fk=0
  AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA=@db AND TABLE_NAME='admin_actions' AND COLUMN_NAME='admin_id')=1,
  'ALTER TABLE admin_actions ADD CONSTRAINT fk_admin_actions_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=@db
    AND TABLE_NAME='admin_actions'
    AND INDEX_NAME='idx_admin_actions_admin_created'
);

SET @sql := IF(
  @has_idx=0,
  'ALTER TABLE admin_actions ADD INDEX idx_admin_actions_admin_created (admin_id, created_at)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
