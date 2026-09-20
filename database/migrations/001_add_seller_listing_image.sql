-- Run once on an existing O2O Tradition database.
-- New installations already receive this column from database/database.sql.
ALTER TABLE seller_listings ADD COLUMN image_path VARCHAR(255) NULL AFTER pickup_option;
