CREATE TABLE IF NOT EXISTS admin_daily_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    rental_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    purchase_revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    rental_orders INT NOT NULL DEFAULT 0,
    purchase_orders INT NOT NULL DEFAULT 0,
    new_customers INT NOT NULL DEFAULT 0,
    new_vendors INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_admin_daily_metric_date (metric_date)
);