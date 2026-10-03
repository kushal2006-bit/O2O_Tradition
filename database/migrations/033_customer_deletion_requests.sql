CREATE TABLE IF NOT EXISTS customer_deletion_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    status ENUM('requested','approved','rejected','cancelled') NOT NULL DEFAULT 'requested',
    reason TEXT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    UNIQUE KEY uq_customer_active_deletion (customer_id,status),
    KEY idx_deletion_status (status,requested_at),
    CONSTRAINT fk_deletion_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_deletion_admin FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;