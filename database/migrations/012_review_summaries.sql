CREATE TABLE IF NOT EXISTS review_summaries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    summary TEXT NOT NULL,
    positive_points JSON NULL,
    common_complaints JSON NULL,
    average_rating DECIMAL(3,2) NULL,
    review_count INT NOT NULL DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_summary_item (item_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);