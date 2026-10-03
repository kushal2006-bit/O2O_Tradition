-- O2O Tradition: idempotent reconciliation for databases created from the older rental prototype.
-- Run after the original rental schema when upgrading an existing installation.
-- New installations should use database/database.sql.

SET @db := DATABASE();

-- Seller listing image column.
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='seller_listings' AND COLUMN_NAME='image_path')=0,
  'ALTER TABLE seller_listings ADD COLUMN image_path VARCHAR(255) NULL AFTER pickup_option',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Rich swap listing fields.
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='swap_listings' AND COLUMN_NAME='title')=0,
  'ALTER TABLE swap_listings ADD COLUMN title VARCHAR(150) NOT NULL DEFAULT ''Swap item'' AFTER item_id',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='swap_listings' AND COLUMN_NAME='condition_label')=0,
  'ALTER TABLE swap_listings ADD COLUMN condition_label ENUM(''new'',''excellent'',''good'',''fair'',''needs_repair'') DEFAULT ''good'' AFTER preferred_item',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='swap_listings' AND COLUMN_NAME='image_path')=0,
  'ALTER TABLE swap_listings ADD COLUMN image_path VARCHAR(255) NULL AFTER condition_label',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Swap offer link.
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='swap_requests' AND COLUMN_NAME='offered_swap_listing_id')=0,
  'ALTER TABLE swap_requests ADD COLUMN offered_swap_listing_id INT NULL AFTER offered_item_id',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='swap_requests' AND CONSTRAINT_NAME='fk_swap_requests_offered_listing');
SET @sql := IF(
  @has_fk=0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='swap_requests' AND COLUMN_NAME='offered_swap_listing_id')=1,
  'ALTER TABLE swap_requests ADD CONSTRAINT fk_swap_requests_offered_listing FOREIGN KEY (offered_swap_listing_id) REFERENCES swap_listings(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Seller purchase orders table.
CREATE TABLE IF NOT EXISTS seller_purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash on Delivery') NOT NULL DEFAULT 'Cash on Delivery',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    order_status ENUM('confirmed','packed','shipped','delivered','cancelled') DEFAULT 'confirmed',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE RESTRICT,
    FOREIGN KEY (buyer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Admin authentication table.
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Audit log used by admin workflows.
CREATE TABLE IF NOT EXISTS admin_actions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
