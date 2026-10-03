ALTER TABLE payment_transactions
    ADD COLUMN provider_refund_id VARCHAR(120) NULL AFTER provider_payment_id,
    ADD COLUMN refund_status ENUM('none','created','processed','failed') NOT NULL DEFAULT 'none' AFTER status,
    ADD KEY idx_payment_refund (provider_refund_id);
