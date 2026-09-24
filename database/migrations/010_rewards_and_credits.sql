-- Rewards ledger integrity, redemption credits, and checkout credit support.
SET @db_name = DATABASE();
SET @rewards_source_type_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rewards' AND COLUMN_NAME='source_type');
SET @sql = IF(@rewards_source_type_exists=0,'ALTER TABLE rewards ADD COLUMN source_type VARCHAR(50) NULL AFTER transaction_type','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @rewards_source_id_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rewards' AND COLUMN_NAME='source_id');
SET @sql = IF(@rewards_source_id_exists=0,'ALTER TABLE rewards ADD COLUMN source_id BIGINT NULL AFTER source_type','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @rewards_source_index_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rewards' AND INDEX_NAME='uq_rewards_source');
SET @sql = IF(@rewards_source_index_exists=0,'ALTER TABLE rewards ADD UNIQUE KEY uq_rewards_source (customer_id, source_type, source_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS reward_credits (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    reward_id BIGINT NOT NULL,
    credit_amount DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reward_credits_customer (customer_id, created_at),
    INDEX idx_reward_credits_balance (customer_id, balance),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE
);
SET @orders_credit_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='orders' AND COLUMN_NAME='reward_credit_used');
SET @sql = IF(@orders_credit_exists=0,'ALTER TABLE orders ADD COLUMN reward_credit_used DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_rent','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @purchase_credit_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='purchase_orders' AND COLUMN_NAME='reward_credit_used');
SET @sql = IF(@purchase_credit_exists=0,'ALTER TABLE purchase_orders ADD COLUMN reward_credit_used DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_amount','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @seller_purchase_credit_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='seller_purchase_orders' AND COLUMN_NAME='reward_credit_used');
SET @sql = IF(@seller_purchase_credit_exists=0,'ALTER TABLE seller_purchase_orders ADD COLUMN reward_credit_used DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_amount','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
