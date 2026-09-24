-- Traditional Attire Rental System Database
-- Run this SQL in phpMyAdmin or MySQL CLI

-- Create/select your own database before importing this file.
-- Example: CREATE DATABASE o2o_tradition CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE o2o_tradition;

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    pincode VARCHAR(10),
    address TEXT,
    password VARCHAR(255) NOT NULL,
    account_status ENUM('active','deactivated') NOT NULL DEFAULT 'active',
    deactivated_at TIMESTAMP NULL,
    email_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    verification_token_hash CHAR(64) NULL,
    verification_expires_at TIMESTAMP NULL,
    verification_last_sent_at TIMESTAMP NULL,
    password_reset_token_hash CHAR(64) NULL,
    password_reset_expires_at TIMESTAMP NULL,
    password_reset_last_sent_at TIMESTAMP NULL,
    failed_login_count INT NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_verification_token (verification_token_hash),
    UNIQUE KEY uq_customers_password_reset_token (password_reset_token_hash)
);

-- Vendors Table
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    pincode VARCHAR(10),
    address TEXT,
    opening_time TIME NULL,
    closing_time TIME NULL,
    pickup_instructions VARCHAR(500) NULL,
    delivery_available TINYINT(1) NOT NULL DEFAULT 0,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    password VARCHAR(255) NOT NULL,
    failed_login_count INT NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items Table
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    quality ENUM('Excellent','Good','Fair') DEFAULT 'Good',
    rent_per_hour DECIMAL(10,2) DEFAULT 0,
    rent_per_day DECIMAL(10,2) NOT NULL,
    late_charge_per_day DECIMAL(10,2) DEFAULT 0,
    image_path VARCHAR(255),
    available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id INT NOT NULL,
    product_size_id INT NULL,
    vendor_id INT NOT NULL,
    delivery_address TEXT NOT NULL,
    payment_method ENUM('Cash on Delivery','Online Payment') NOT NULL,
    payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    pickup_date DATE NOT NULL,
    return_date DATE NOT NULL,
    actual_return_date DATE,
    total_rent DECIMAL(10,2),
    security_deposit DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    final_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    late_days INT DEFAULT 0,
    late_charges DECIMAL(10,2) DEFAULT 0,
    reward_credit_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('new','in_progress','completed','cancelled') DEFAULT 'new',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- ============================================================
-- O2O Tradition marketplace extensions
-- The legacy rental tables above are kept for compatibility with
-- the current PHP prototype. New marketplace features use the
-- tables below.
-- ============================================================

CREATE TABLE IF NOT EXISTS product_modes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    mode ENUM('rent','buy','sell','swap') NOT NULL,
    price DECIMAL(10,2) DEFAULT 0,
    security_deposit DECIMAL(10,2) DEFAULT 0,
    available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_mode (item_id, mode),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    vendor_id INT,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    reward_credit_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Cash on Delivery','Online Payment') NOT NULL DEFAULT 'Cash on Delivery',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    order_status ENUM('pending','confirmed','packed','shipped','delivered','cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    product_size_id INT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS seller_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    item_id INT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    condition_label ENUM('new','excellent','good','fair','needs_repair') DEFAULT 'good',
    status ENUM('draft','pending_review','active','sold','cancelled') DEFAULT 'draft',
    pickup_option TINYINT(1) DEFAULT 1,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS swap_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    item_id INT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    preferred_category VARCHAR(100),
    preferred_item VARCHAR(150),
    condition_label ENUM('new','excellent','good','fair','needs_repair') DEFAULT 'good',
    image_path VARCHAR(255),
    location VARCHAR(255),
    status ENUM('active','matched','completed','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS swap_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    swap_listing_id INT NOT NULL,
    requester_id INT NOT NULL,
    offered_item_id INT NULL,
    message TEXT,
    offered_swap_listing_id INT NULL,
    status ENUM('pending','accepted','rejected','cancelled','completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (swap_listing_id) REFERENCES swap_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (offered_item_id) REFERENCES items(id) ON DELETE SET NULL,
    FOREIGN KEY (offered_swap_listing_id) REFERENCES swap_listings(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id INT NULL,
    vendor_id INT NULL,
    rating TINYINT NOT NULL,
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    CHECK (rating BETWEEN 1 AND 5)
);

CREATE TABLE IF NOT EXISTS customer_assistant_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_assistant (customer_id, created_at)
);

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

CREATE TABLE IF NOT EXISTS complaints (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_type ENUM('rental','purchase','sell_purchase') NOT NULL,
    order_id INT NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    status ENUM('open','in_review','resolved','closed') NOT NULL DEFAULT 'open',
    admin_response TEXT NULL,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_complaints_status (status, created_at),
    INDEX idx_complaints_customer (customer_id, created_at)
);

CREATE TABLE IF NOT EXISTS demand_insights (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    insight TEXT NOT NULL,
    recommendations JSON NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_demand_insights_vendor (vendor_id, generated_at)
);

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

CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (customer_id, item_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_events_customer (customer_id, created_at),
    INDEX idx_user_events_item (item_id, created_at),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_customer (customer_id, created_at),
    INDEX idx_notifications_unread (customer_id, is_read, created_at),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

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

CREATE TABLE IF NOT EXISTS rewards (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    transaction_type ENUM('earn','redeem','adjustment') NOT NULL DEFAULT 'earn',
    source_type VARCHAR(50) NULL,
    source_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rewards_source (customer_id, source_type, source_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

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

CREATE TABLE IF NOT EXISTS payment_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_type ENUM('rental','purchase') NOT NULL,
    order_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'razorpay',
    provider_order_id VARCHAR(80) NOT NULL,
    provider_payment_id VARCHAR(80) NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    status ENUM('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
    signature VARCHAR(128) NULL,
    failure_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    UNIQUE KEY uq_payment_provider_order (provider, provider_order_id),
    UNIQUE KEY uq_payment_order (order_type, order_id),
    UNIQUE KEY uq_payment_provider_payment (provider, provider_payment_id),
    INDEX idx_payment_customer (customer_id, created_at),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS vendor_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    verification_type VARCHAR(50) NOT NULL,
    document_path VARCHAR(255),
    status ENUM('pending','verified','rejected') DEFAULT 'pending',
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS condition_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    rental_order_id INT NULL,
    created_by_vendor_id INT NULL,
    inspection_type ENUM('before_rental','after_return','manual') NOT NULL,
    condition_score DECIMAL(5,2),
    notes TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (rental_order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sanitization_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    rental_order_id INT NULL,
    created_by_vendor_id INT NULL,
    status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (rental_order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
);

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

CREATE TABLE IF NOT EXISTS user_style_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL UNIQUE,
    preferred_colors TEXT,
    preferred_styles TEXT,
    preferred_categories TEXT,
    budget_min DECIMAL(10,2),
    budget_max DECIMAL(10,2),
    favorite_occasions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_avatars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    photo_path VARCHAR(255),
    height DECIMAL(6,2),
    measurements JSON,
    preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tryon_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    avatar_id INT NULL,
    item_id INT NULL,
    input_image VARCHAR(255),
    result_image VARCHAR(255),
    status ENUM('queued','processing','completed','failed') DEFAULT 'queued',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (avatar_id) REFERENCES user_avatars(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS size_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL UNIQUE,
    height DECIMAL(6,2),
    chest DECIMAL(6,2),
    waist DECIMAL(6,2),
    hip DECIMAL(6,2),
    shoulder DECIMAL(6,2),
    sleeve DECIMAL(6,2),
    shoe_size VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    size_label VARCHAR(30) NOT NULL,
    chest DECIMAL(6,2),
    waist DECIMAL(6,2),
    hip DECIMAL(6,2),
    length DECIMAL(6,2),
    available TINYINT(1) DEFAULT 1,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS review_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL UNIQUE,
    summary TEXT NOT NULL,
    positive_points JSON,
    common_complaints JSON,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS condition_ai_reports (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    vendor_id INT NULL,
    image_path VARCHAR(255) NOT NULL,
    detected_issues JSON,
    condition_assessment VARCHAR(255),
    confidence DECIMAL(5,4),
    status ENUM('queued','processing','completed','failed') DEFAULT 'queued',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    failed_login_count INT NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_actions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_actions_admin_created (admin_id, created_at),
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- This file intentionally contains schema only.
-- Demo data belongs in database/seed_demo.sql

CREATE TABLE IF NOT EXISTS seller_purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    reward_credit_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Cash on Delivery') NOT NULL DEFAULT 'Cash on Delivery',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    order_status ENUM('confirmed','packed','shipped','delivered','cancelled') DEFAULT 'confirmed',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES seller_listings(id) ON DELETE RESTRICT,
    FOREIGN KEY (buyer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES customers(id) ON DELETE CASCADE
);
