-- Allow rental orders to be explicitly cancelled when an online payment fails or expires.
SET @db_name = DATABASE();
SET @sql = IF(
  EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='status'),
  "ALTER TABLE orders MODIFY status ENUM('new','in_progress','completed','cancelled') DEFAULT 'new'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;