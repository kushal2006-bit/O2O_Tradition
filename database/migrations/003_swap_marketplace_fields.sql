-- Add richer community swap listing fields and link swap offers to another swap listing.
ALTER TABLE swap_listings
  ADD COLUMN title VARCHAR(150) NOT NULL AFTER item_id,
  ADD COLUMN condition_label ENUM('new','excellent','good','fair','needs_repair') DEFAULT 'good' AFTER preferred_item,
  ADD COLUMN image_path VARCHAR(255) NULL AFTER condition_label;

ALTER TABLE swap_requests
  ADD COLUMN offered_swap_listing_id INT NULL AFTER offered_item_id,
  ADD CONSTRAINT fk_swap_requests_offered_listing
    FOREIGN KEY (offered_swap_listing_id) REFERENCES swap_listings(id) ON DELETE SET NULL;
