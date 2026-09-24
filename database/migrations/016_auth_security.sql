ALTER TABLE customers
    ADD COLUMN email_verified_at TIMESTAMP NULL,
    ADD COLUMN verification_token_hash CHAR(64) NULL,
    ADD COLUMN verification_expires_at TIMESTAMP NULL,
    ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0,
    ADD COLUMN locked_until TIMESTAMP NULL;
ALTER TABLE customers ADD UNIQUE KEY uq_customers_verification_token (verification_token_hash);
