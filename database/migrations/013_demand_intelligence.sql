CREATE TABLE IF NOT EXISTS demand_insights (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    insight TEXT NOT NULL,
    recommendations JSON NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_demand_insights_vendor (vendor_id, generated_at)
);