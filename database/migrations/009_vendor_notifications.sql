-- Vendor notification inbox for marketplace and rental events.
CREATE TABLE IF NOT EXISTS vendor_notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vendor_notifications_vendor (vendor_id, created_at),
    INDEX idx_vendor_notifications_unread (vendor_id, is_read, created_at),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);
