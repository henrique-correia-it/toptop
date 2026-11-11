# TopTop — E-commerce Platform

**Online store built from scratch in PHP and MySQL, live in production at [toptop.pt](https://toptop.pt): product variations, Stripe payments, transactional stock reservation and a full admin back office.**

![PHP](https://img.shields.io/badge/PHP_8-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)
![Stripe](https://img.shields.io/badge/Stripe-635BFF?logo=stripe&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)
![Status: live](https://img.shields.io/badge/status-live_in_production-2EA44F)

> **Personal project** by **Henrique Correia** (2025 - present), built for a friend's store. Designed, built, deployed and hosted by me.
> 🌐 **Live:** <https://toptop.pt>
> No framework and no Composer dependencies, about 23,000 lines of PHP.

## Highlights

- **Payments without the SDK:**
  - Stripe Payment Element and PaymentIntents through a small cURL client;
  - **webhook signature checked by hand**: HMAC-SHA256 with a 5-minute timestamp tolerance;
  - refunds from the back office;
  - switchable test and live modes, each with its own keys.
- **Stock you can trust:**
  - stock is reserved when checkout starts;
  - order items and stock changes are written in **database transactions**;
  - abandoned checkouts release their reservation after 30 minutes;
  - prices and stock are checked again on the server before payment.
- **Security:**
  - **CSRF tokens** on forms and AJAX actions;
  - hardened session cookies (HttpOnly, Secure, SameSite);
  - `password_hash` for passwords and prepared statements throughout;
  - customers look up orders with a secure token;
  - scripts cannot run inside upload folders.
- **Back office with access levels:** separate admin and developer roles for products, variations, customers, orders, shipping and content.
- **Content managed in the browser:**
  - header, footer, home page and interface text are edited in place;
  - developer tools for logs, database backups, storage usage and an image gallery.
- **Service layer:** cart, order, customer and shipping logic lives in reusable services (`includes/*Service.php`) instead of being spread across pages.

## Features

**Storefront**
- Catalogue with categories, attributes and variations (for example size and colour), search, related products and size guides.
- Cart, checkout with Stripe, order confirmation and order tracking.
- Customer accounts with email verification and password recovery.
- Saved addresses and order history.
- Shipping cost calculation, with a free-shipping threshold you can configure.
- Contact form, returns page, sitemap and `robots.txt`.

**Back office**
- Products and variations: create, edit, duplicate, quick-edit, order and change visibility in bulk.
- Orders: status, details, refunds, bulk actions and cancelling reservations.
- Customers and admin accounts: activate, deactivate and delete in bulk.
- Shipping rules, store settings and transactional emails (PHPMailer over SMTP).

## Architecture

```text
Browser (HTML, CSS, vanilla JS, AJAX)
        │
Apache + .htaccess (blocks direct access to config and hidden files, upload protection)
        │
PHP 8 pages and AJAX endpoints
 ├── config/      session, CSRF, auth and roles, Stripe client, logger, URL helpers
 ├── includes/    CartService · OrderService · CustomerService · ShippingService
 ├── templates/   header, footer, customer sidebar
 ├── admin/       back office (role-based)
 └── dev/         developer tools (logs, backups, live content editing)
        │                                   │
MySQL / MariaDB (prepared statements,   Stripe API (PaymentIntents, refunds)
transactions, stock reservations)       ◄── signed webhook (stripe_webhook.php)
        │
PHPMailer (SMTP) for transactional email
```

## Tech stack

| Area | Technology |
|---|---|
| Backend | PHP 8 (no framework), MySQL/MariaDB (mysqli) |
| Payments | Stripe Payment Element, PaymentIntents, webhooks, refunds (REST over cURL) |
| Frontend | HTML, CSS, vanilla JavaScript, AJAX |
| Email | PHPMailer 6.10 over SMTP |
| Hosting | Apache with `mod_rewrite`, cPanel, HTTPS |

## Running locally

Requires PHP 8+ (`mysqli`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `session`), MySQL/MariaDB and Apache with `mod_rewrite`.

1. Copy `.env.example` to `.env` and fill in:
   - the database settings;
   - the Stripe test and live keys and webhook secrets;
   - the SMTP settings;
   - `APP_URL`.

   `.env` is ignored by Git.
2. Create the database schema the application needs.
3. Point the web root at the project and allow `.htaccess` overrides.
4. In the Stripe dashboard, add a webhook pointing to `/stripe_webhook.php` for the `payment_intent.*` events.
5. Give write permission only to the image and log folders.

Product photos, logos and other store images belong to the store and are **not** included. Raster images are excluded by `.gitignore`.

## Security notes

- Real `.env` files, logs, database dumps, backups, keys and certificates are never committed.
- `public/images/.htaccess` stops scripts from running in the uploads folder.
- Production runs only over HTTPS.

## Third-party components and license

PHPMailer 6.10.0 is included under its own LGPL-2.1 license (see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)).

Copyright © 2026 Henrique Correia. **All rights reserved.** The code is public for reference only; see [LICENSE](LICENSE).
