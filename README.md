# O2O Tradition

O2O Tradition is a hyperlocal traditional-wear marketplace for **Rent, Buy, Sell & Swap**, with AI-powered style bundles, size guidance, condition assistance, and virtual try-on.

## Current development branch

The `development` branch contains the evolving marketplace implementation. The original Vasanam rental flow remains the compatibility baseline.

### Implemented in development

- Customer and vendor authentication
- Smart homepage with Rent / Buy / Sell / Swap modes
- Pincode-based store and product discovery
- Rental checkout, order history, tracking, and late-charge handling
- Vendor inventory and image upload
- Buy flow and buy-order tracking
- Customer Sell listings, vendor review, seller sales management, and pre-owned purchase flow
- Swap listings and swap-request workflow
- Vendor verification submission and verified-vendor display
- Condition reports and sanitization records
- AI condition-assistant workflow
- Customer reviews and AI review summaries
- AI Personal Stylist
- AI Complete Look
- Size profile and deterministic Size & Fit guidance
- AI Avatar profile
- Virtual Try-On workflow using the Hugging Face IDM-VTON Space integration
- Current recommendation, notification, rewards, occasion, map, rental-tracking, wishlist, and customer-assistant foundations

## Virtual Try-On configuration

The current development implementation uses the free Hugging Face IDM-VTON ZeroGPU Space for prototype/judge demonstration.

The HF token must **never** be committed to GitHub or pasted into chat.

The try-on code supports either:
- server environment variable `HF_TOKEN`, or
- a private server PHP file at `/home/o2otra0675/secure/hf_token.php` defining `$HF_TOKEN`.

The private token file is outside the public project directory and is not part of this repository.

The IDM-VTON integration is a prototype/demo integration. Its model license and the limits of the free ZeroGPU service must be considered before production/commercial deployment.

## Repository structure

```text
O2O_Tradition/
├── customer/
├── vendor/
├── admin/
├── shared/
├── uploads/
├── database/
│   ├── database.sql
│   ├── seed_demo.sql
│   └── migrations/
├── .gitignore
└── README.md
```

## Local setup

1. Create a MySQL database.
2. Import `database/database.sql`.
3. If needed for development, import `database/seed_demo.sql`.
4. Copy `shared/config.example.php` to `shared/config.php`.
5. Set database values in `shared/config.php or provide `O2O_DB_HOST`, `O2O_DB_NAME`, `O2O_DB_USER`, and `O2O_DB_PASS`.
6. Ensure upload folders are writable by PHP.
7. For virtual try-on, configure `HF_TOKEN` as a server environment variable or use a private PHP secret file outside the public web root.
8. Open the customer or vendor login page through a PHP web server.

## Security

- Session cookies use HttpOnly, Secure-on-HTTPS, and SameSite=Lax settings.
- Authentication regenerates the session ID after successful login.
- State-changing marketplace forms use CSRF protection.
- Private media/document endpoints require authorization and send restrictive response headers.
- Uploaded images are validated by extension and detected MIME type where applicable.

- Real credentials and API tokens are never committed to GitHub.
- The live hosting `config.php` is intentionally excluded.
- The original ZIP's `.git` metadata and packaging artifacts are not imported.
- Runtime uploads are ignored by Git.
- Sensitive upload directories have access-control rules.
- Demo credentials are for development only.

## Development rule

A feature should only be described as live after its backend, UI, and real-world flow have been implemented and tested on the target environment.

## Roadmap

1. Complete and harden core marketplace flows.
2. Complete and test the AI layer.
3. Complete trust, condition, review, and rental intelligence.
4. Complete discovery, recommendations, maps, notifications, and rewards.
5. Complete business/admin intelligence, moderation, disputes, analytics, and production hardening.
