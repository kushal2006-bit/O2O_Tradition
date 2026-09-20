# O2O Tradition

O2O Tradition is a hyperlocal traditional-wear marketplace for **Rent, Buy, Sell & Swap**, with planned AI-powered style bundles, size guidance, condition assistance, and virtual try-on.

## Current repository baseline

This repository starts from the working Vasanam traditional-attire rental prototype and keeps its customer and vendor rental flows as the compatibility baseline.

### Included now

- Customer login/signup and session authentication
- Vendor login and vendor dashboard
- Traditional-attire search and store discovery by pincode
- Rental item details and rental checkout
- Rental order tracking and late-charge handling
- Vendor inventory management and image upload flow
- PDO/prepared-statement database access
- Expanded database schema for Buy, Sell, Swap, trust, reviews, AI data, recommendations, notifications, and rewards

### Not implemented yet

The new marketplace and AI tables are schema foundations only. Buy, Sell, Swap, AI stylist, avatar, virtual try-on, recommendations, maps, notifications, rewards, admin, and other planned features still need application code and testing.

## Repository structure

```text
O2O_Tradition/
├── customer/
├── vendor/
├── shared/
│   └── config.example.php
├── uploads/
│   ├── items/
│   ├── avatars/
│   └── condition-reports/
├── database/
│   ├── database.sql
│   └── seed_demo.sql
├── .gitignore
└── README.md
```

## Local setup

1. Create a MySQL database, for example `o2o_tradition`.
2. Import `database/database.sql`.
3. If you want demo records, import `database/seed_demo.sql`.
4. Copy `shared/config.example.php` to `shared/config.php`.
5. Set the database values in `shared/config.php` or provide `O2O_DB_HOST`, `O2O_DB_NAME`, `O2O_DB_USER`, and `O2O_DB_PASS`.
6. Ensure the upload folders are writable by PHP.
7. Open `customer/login.php` or `vendor/login.php` through your PHP web server.

## Security

- Real credentials are never committed to GitHub.
- The live hosting `config.php` is intentionally excluded.
- The original ZIP's `.git` metadata and packaging artifacts are not imported.
- Runtime uploads are ignored by Git.
- Demo credentials in `database/seed_demo.sql` are for development only.

## Product roadmap

1. Core marketplace: Buy, Sell, Swap, hyperlocal discovery, vendor store pages, transparent pricing, and unified product modes.
2. AI layer: personal stylist, complete-look bundles, avatar, virtual try-on, and size/fit guidance.
3. Trust and intelligence: verification, condition reports, review summaries, and rental tracking.
4. Discovery and retention: occasions, recommendations, maps, notifications, rewards, and circular-fashion history.
5. Business/admin: demand intelligence, customer assistant, moderation, disputes, and analytics.

A feature should only be described as live after its backend, UI, and real-world flow have been implemented and tested.
