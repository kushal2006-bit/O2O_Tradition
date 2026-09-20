ALTER TABLE condition_ai_reports
  ADD COLUMN vendor_id INT NULL AFTER item_id,
  ADD COLUMN status ENUM('queued','processing','completed','failed') DEFAULT 'queued' AFTER confidence,
  ADD COLUMN notes TEXT NULL AFTER status,
  ADD CONSTRAINT fk_condition_ai_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;
