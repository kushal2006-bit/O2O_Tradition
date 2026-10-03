-- Run once on an existing O2O Tradition database.
CREATE TABLE IF NOT EXISTS seller_purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash on Delivery') NOT NULL DEFAULT 'Cash on Delivery',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    order_status ENUM('confirmed','packed','shipped','delivered','cancelled') DEFAULT 'confirmed',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE RESTRICT,
    FOREIGN KEY (buyer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES customers(id) ON DELETE CASCADE
);
