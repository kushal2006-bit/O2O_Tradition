CREATE TABLE IF NOT EXISTS eco_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    event_type ENUM('rental_completed','swap_completed','resale_completed','upcycled') NOT NULL,
    source_id BIGINT NULL,
    reuse_count INT NOT NULL DEFAULT 1,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_eco_source (customer_id, event_type, source_id),
    INDEX idx_eco_customer (customer_id, created_at)
);