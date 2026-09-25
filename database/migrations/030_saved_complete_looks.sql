CREATE TABLE IF NOT EXISTS saved_complete_looks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    occasion VARCHAR(100) NOT NULL,
    mode ENUM('rent','buy') NOT NULL,
    budget DECIMAL(10,2) NULL,
    style VARCHAR(255) NULL,
    colors VARCHAR(255) NULL,
    intro TEXT NULL,
    look_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_saved_looks_customer (customer_id, created_at)
);