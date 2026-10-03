-- O2O Tradition development/demo data.
-- Use only in a development database.

INSERT INTO vendors (store_name, email, phone, pincode, address, password) VALUES
('Royal Attire House', 'vendor@attire.com', '9876543210', '400001', 'Shop 12, Heritage Lane, Mumbai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Traditions & More', 'vendor2@attire.com', '9988776655', '400002', 'Block B, Silk Road, Mumbai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Heritage Wears', 'vendor3@attire.com', '9911223344', '400003', '5th Floor, Cotton Exchange, Mumbai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO customers (name, email, phone, pincode, address, password) VALUES
('Demo Customer', 'customer@test.com', '9000000000', '400001', '123 Test Street, Mumbai', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO items (vendor_id, name, description, category, quality, rent_per_hour, rent_per_day, late_charge_per_day) VALUES
(1, 'Banarasi Silk Saree', 'Exquisite Banarasi silk saree with gold zari work. Perfect for weddings and festivals.', 'Saree', 'Excellent', 50, 500, 100),
(1, 'Sherwani Set', 'Royal navy blue sherwani with intricate embroidery and churidar. Comes with dupatta.', 'Sherwani', 'Excellent', 80, 800, 150),
(1, 'Lehenga Choli', 'Vibrant red lehenga choli with mirror work. Ideal for sangeet and wedding functions.', 'Lehenga', 'Good', 60, 600, 120),
(2, 'Punjabi Phulkari Suit', 'Traditional Punjabi suit with handcrafted Phulkari embroidery in bright colors.', 'Suit', 'Good', 40, 400, 80),
(2, 'Rajasthani Ghagra', 'Colorful Rajasthani ghagra choli with mirror and thread work. Very festive.', 'Ghagra', 'Excellent', 55, 550, 110),
(3, 'Dhoti Kurta Set', 'Classic white dhoti kurta with gold border. Perfect for puja and traditional events.', 'Dhoti', 'Good', 30, 300, 60),
(3, 'Anarkali Suit', 'Floor-length Anarkali suit in deep teal with heavy embroidery at neckline and hem.', 'Anarkali', 'Excellent', 70, 700, 140);

-- Default demo account password is: password

INSERT INTO admins (name, email, password) VALUES
('Demo Admin', 'admin@o2otradition.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password);

-- Demo admin password is: password
