# cPanel Production Deployment Checklist

This checklist is for deploying the `development` branch to a real cPanel/PHP/MySQL target. It does not contain production secrets.

## 1. Before deployment

- Confirm the target uses PHP 8.2 or a compatible newer PHP version.
- Enable PHP extensions used by the application: `pdo_mysql`, `fileinfo`, and `curl`.
- Create the production MySQL database and database user.
- Confirm HTTPS is active for the final application domain.
- Confirm the hosting account provides CLI PHP for cron jobs and migrations.
- Do not upload or expose `.git`, local development files, real credentials, or test data.

## 2. Upload the application

Upload the repository contents to the intended document root.

The repository root `.htaccess` blocks direct web access to:

- `database/`
- `tests/`
- `cron/`
- source/configuration files matched by the root rules

Keep those protections enabled. Do not replace the root `.htaccess` with a permissive cPanel-generated file.

Create the private runtime configuration as:

`shared/config.php`

This file is intentionally ignored by Git and must never be committed.

## 3. Production configuration

Configure the values in the PHP/cPanel runtime. The database and Razorpay values are consumed by `shared/config.php`, but the application/email and external-service values below are read directly from the PHP environment; do not assume that adding them to `shared/config.php` will configure them unless the application code is changed to support that.

### Required database values

- `O2O_DB_HOST`
- `O2O_DB_NAME`
- `O2O_DB_USER`
- `O2O_DB_PASS`

### Application/email

- `O2O_APP_URL` — must be the final HTTPS application URL.
- `O2O_MAIL_FROM` — must be a valid sender email address with no CR/LF characters.

### Payments

For real online checkout, configure all of:

- `O2O_RAZORPAY_KEY_ID`
- `O2O_RAZORPAY_KEY_SECRET`
- `O2O_RAZORPAY_WEBHOOK_SECRET`

Use production Razorpay credentials only on the production server.

Configure the Razorpay webhook to reach:

`/customer/payment_webhook.php`

Use the exact HTTPS production URL and the same webhook secret configured on the server.

### AI / external services

- `OPENAI_API_KEY` for OpenAI-backed assistant functionality.
- `O2O_AI_MODEL` is optional; the current default is `gpt-5.6-luna`.
- `HF_TOKEN` is required only if the virtual try-on integration is enabled.
- `O2O_HF_TOKEN_FILE` is optional; when set, it points to a private PHP file outside the public web root that defines `$HF_TOKEN`. This is an alternative to `HF_TOKEN` for the virtual try-on integration.

Never place API keys in Git, HTML, JavaScript, screenshots, or chat messages.

## 4. Database installation

For a new production database:

1. Import `database/database.sql`.
2. Do not import `database/seed_demo.sql` unless explicitly needed for a non-production environment.
3. From the project root, run the migration runner through CLI PHP:

```bash
php database/migrate.php --status
php database/migrate.php
```

The migration runner is CLI-only and takes a MySQL advisory lock before changing the schema.

If the application is already using the migration table, apply only the pending migrations. Do not manually delete rows from `schema_migrations`.

## 5. Upload directories

Ensure PHP can write to the required runtime upload directories while keeping directory listing disabled.

Important directories include:

- `uploads/items/`
- `uploads/avatars/`
- `uploads/condition-reports/`
- `uploads/sell/`
- `uploads/swap/`
- `uploads/condition-ai/`
- `uploads/tryon/`
- `uploads/vendor-verification/`

Keep the repository's upload-directory access controls in place. Uploaded files must not become executable PHP.

## 6. Cron jobs

Configure cPanel Cron Jobs to invoke PHP CLI, not a browser URL.

Recommended commands from the project root:

```bash
php cron/expire_pending_payments.php
php cron/wishlist_alerts.php
```

Run the pending-payment expiry job frequently enough to clear abandoned payment attempts. Run wishlist alerts on a regular schedule appropriate for the business.

Both scripts reject non-CLI execution.

## 7. First deployment smoke test

After HTTPS and configuration are active:

1. Open customer login.
2. Open vendor login.
3. Open admin login.
4. Verify unauthenticated access is redirected/denied where expected.
5. Create or verify a customer account.
6. Exercise one non-payment customer flow.
7. Exercise one vendor workflow.
8. Exercise one admin workflow.
9. Confirm email configuration works.
10. Confirm uploads are accepted only through the intended application flow.
11. Confirm direct requests to `/database/`, `/tests/`, and `/cron/` are denied.
12. Confirm `shared/config.php` cannot be downloaded.
13. Confirm HTTPS is enforced by the hosting configuration.
14. Run the acceptance contract gate from the deployed code if CLI PHP is available:

```bash
php tests/acceptance_contracts.php
```

## 8. Payment acceptance

Use Razorpay test mode before production credentials.

Verify both callback and webhook paths:

- successful payment
- invalid signature
- wrong amount
- wrong currency
- wrong provider order
- duplicate webhook delivery
- captured payment state
- refund processed
- refund failed / retry path

Do not mark a deployment payment-ready solely because the PHP contract gate passes; the real gateway must be exercised in the target environment.

## 9. AI acceptance

Verify the customer assistant with:

- a normal product/catalogue question
- an oversized question
- prompt-injection-like customer text
- a provider timeout/error

Verify that the application does not expose provider credentials or invent account/order facts.

The virtual try-on integration should be treated separately from the core marketplace acceptance because the external free service has its own availability and licensing constraints.

## 10. Rollback

Before changing an existing production deployment:

- Take a database backup.
- Keep a copy of the previous application release.
- Record the currently deployed commit SHA.
- Apply schema migrations only after the application version is known to be compatible.
- If an application rollback is required after a migration, verify schema compatibility before restoring old code.

Never roll back by deleting migration records or by blindly reversing production data changes.

## 11. Final release record

Record:

- deployed Git commit SHA
- deployment timestamp
- PHP version
- MySQL/MariaDB version
- application HTTPS URL
- migration status
- cron schedules
- Razorpay webhook status
- email sender status
- AI provider configuration status

Do not record secret values themselves.
