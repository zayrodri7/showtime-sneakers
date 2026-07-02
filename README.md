# ShowtimeSneakers

A small PHP + MySQL sneaker store: product catalog with per-size stock, a session
cart, checkout, order confirmation email, and an admin dashboard for orders and
inventory. Runs locally on XAMPP and deploys to Render with a hosted MySQL database.

## What changed from the original blueprints

The original storefront (`index.php`, `cart.php`, `checkout.php`, `send_order.php`)
used hardcoded PHP arrays and had no sizes or stock, while `admin.php` expected real
database tables. Those two halves didn't connect. This rebuild makes the whole app
database-driven and consistent:

- Catalog, sizes, and stock all come from MySQL.
- Add-to-cart requires choosing an in-stock size.
- Checkout saves a real order + line items and decrements only the purchased
  shoe/size stock, inside a transaction (no overselling the last pair).
- Cancelling an order in the admin restores that stock.
- `db.php` reads all connection settings from environment variables and
  auto-creates + seeds the tables on first run, so it works on Render without
  phpMyAdmin.

## Files

| File | Purpose |
|------|---------|
| `index.php` | Product catalog with size selector |
| `cart.php` | Session cart (per shoe + size line items) |
| `checkout.php` | Customer info form |
| `send_order.php` | Saves order, decrements stock, emails, clears cart |
| `admin_login.php` / `admin.php` / `admin_logout.php` | Admin auth + dashboard |
| `db.php` | PDO connection + auto schema/seed |
| `schema.sql` | Manual schema + seed for phpMyAdmin |
| `Dockerfile` / `docker-entrypoint.sh` / `render.yaml` | Deployment |
| `assets/` | Placeholder sneaker images (swap in real PNGs) |

## Run locally with XAMPP

1. Install and start XAMPP (Apache + MySQL).
2. Copy this folder into `xampp/htdocs/`, e.g. `xampp/htdocs/sneaker-store/`.
3. In phpMyAdmin, create a database named `sneaker_store`.
   - You can import `schema.sql`, **or** just skip this and let `db.php`
     auto-create the tables the first time you load the site.
4. Visit http://localhost/sneaker-store/index.php

Default local DB settings (host `localhost`, user `root`, empty password) are the
XAMPP defaults, so no config is needed.

**Admin:** go to `admin_login.php` — username `IR247`, password `ADMIN123$`.

## Deploy to Render (with hosted MySQL)

Render doesn't run plain PHP the way XAMPP does, so this repo ships a `Dockerfile`.
The database must be hosted too (not your local XAMPP) — a free
[Aiven for MySQL](https://aiven.io/) instance works well.

1. Create a MySQL database with your provider and note its host, port, database
   name, user, and password.
2. Push this repo to GitHub.
3. In Render: **New + → Web Service → Build from a Dockerfile** (or use
   **Blueprint** with the included `render.yaml`).
4. Set these environment variables on the service:

   | Variable | Example | Notes |
   |----------|---------|-------|
   | `DB_HOST` | `mysql-xxxx.aivencloud.com` | from your DB provider |
   | `DB_PORT` | `3306` (Aiven often uses a custom port) | |
   | `DB_NAME` | `sneaker_store` | create this DB on the provider first |
   | `DB_USER` | `avnadmin` | |
   | `DB_PASS` | `••••••` | |
   | `DB_SSL` | `true` | required by most hosted MySQL |
   | `STORE_EMAIL` | `you@example.com` | where order emails go |
   | `ADMIN_USER` | `IR247` | optional override |
   | `ADMIN_PASS` | `ADMIN123$` | optional override |

5. Deploy. On first load, `db.php` creates and seeds the tables automatically.
   If your DB user lacks `CREATE` privileges, import `schema.sql` manually instead.

## Notes

- **Email:** PHP's `mail()` won't send on XAMPP or Render without an SMTP setup.
  The order is always saved to the database regardless, and the confirmation page
  shows the full order details. For real email, integrate an SMTP library
  (e.g. PHPMailer) with your provider's credentials.
- **Images:** `assets/` holds the real product photos for the Air Force 1, Jordan 4,
  New Balance 550, and Yeezy 350. The Adidas Samba uses a generated placeholder
  (`samba.png`) — drop in a real photo with that filename to replace it.
- **Security:** admin credentials live in env vars and login uses constant-time
  comparison. For production you'd want hashed passwords in the database and HTTPS
  (Render provides HTTPS automatically).
