-- Reconcile richer community swap fields safely on an existing database.
SET @has_title := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='swap_listings' AND COLUMN_NAME='title');
SET @sql := IF(@has_title=0,'ALTER TABLE swap_listings ADD COLUMN title VARCHAR(150) NOT NULL AFTER item_id','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_condition := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='swap_listings' AND COLUMN_NAME='condition_label');
SET @sql := IF(@has_condition=0,"ALTER TABLE swap_listings ADD COLUMN condition_label ENUM('new','excellent','good','fair','needs_repair') DEFAULT 'good' AFTER preferred_item",'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_image := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='swap_listings' AND COLUMN_NAME='image_path');
SET @sql := IF(@has_image=0,'ALTER TABLE swap_listings ADD COLUMN image_path VARCHAR(255) NULL AFTER condition_label','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_offer := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='swap_requests' AND COLUMN_NAME='offered_swap_listing_id');
SET @sql := IF(@has_offer=0,'ALTER TABLE swap_requests ADD COLUMN offered_swap_listing_id INT NULL AFTER offered_item_id','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='swap_requests' AND CONSTRAINT_NAME='fk_swap_requests_offered_listing');
SET @sql := IF(@has_fk=0,'ALTER TABLE swap_requests ADD CONSTRAINT fk_swap_requests_offered_listing FOREIGN KEY (offered_swap_listing_id) REFERENCES swap_listings(id) ON DELETE SET NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
