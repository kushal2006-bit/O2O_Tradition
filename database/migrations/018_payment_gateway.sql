-- Payment gateway foundation for Razorpay Standard Checkout.
SET @db_name = DATABASE();

SET @orders_payment_status_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='payment_status'
);
SET @sql = IF(@orders_payment_status_exists=0,
  "ALTER TABLE orders ADD COLUMN payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending' AFTER payment_method",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orders_payment_method_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='payment_method'
);
SET @sql = IF(@orders_payment_method_exists=1,
  "ALTER TABLE orders MODIFY payment_method ENUM('Cash on Delivery','Online Payment') NOT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @purchase_payment_method_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='purchase_orders' AND COLUMN_NAME='payment_method'
);
SET @sql = IF(@purchase_payment_method_exists=0,
  "ALTER TABLE purchase_orders ADD COLUMN payment_method ENUM('Cash on Delivery','Online Payment') NOT NULL DEFAULT 'Cash on Delivery' AFTER delivery_charge",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_type ENUM('rental','purchase') NOT NULL,
    order_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'razorpay',
    provider_order_id VARCHAR(80) NOT NULL,
    provider_payment_id VARCHAR(80) NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    status ENUM('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
    signature VARCHAR(128) NULL,
    failure_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    UNIQUE KEY uq_payment_provider_order (provider, provider_order_id),
    UNIQUE KEY uq_payment_order (order_type, order_id),
    UNIQUE KEY uq_payment_provider_payment (provider, provider_payment_id),
    INDEX idx_payment_customer (customer_id, created_at),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);