-- Add condition report and sanitization controls for vendor-managed rental items.
ALTER TABLE condition_reports
  ADD COLUMN created_by_vendor_id INT NULL AFTER rental_order_id,
  ADD CONSTRAINT fk_condition_reports_vendor FOREIGN KEY (created_by_vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;

ALTER TABLE sanitization_records
  ADD COLUMN created_by_vendor_id INT NULL AFTER rental_order_id,
  ADD CONSTRAINT fk_sanitization_records_vendor FOREIGN KEY (created_by_vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;
