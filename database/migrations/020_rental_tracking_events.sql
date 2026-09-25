CREATE TABLE IF NOT EXISTS rental_tracking_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    rental_order_id INT NOT NULL,
    vendor_id INT NOT NULL,
    event_type ENUM('confirmed','picked_up','returned','inspected','sanitized','cancelled') NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rental_tracking_event (rental_order_id, event_type),
    INDEX idx_rental_tracking_order (rental_order_id, created_at),
    INDEX idx_rental_tracking_vendor (vendor_id, created_at),
    FOREIGN KEY (rental_order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);