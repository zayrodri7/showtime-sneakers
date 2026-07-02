# ShowtimeSneakers

A simple PHP + MySQL sneaker store. Customers browse shoes, pick a size,
add pairs to a cart, and check out. Every order is saved to the database
and the correct shoe/size stock is reduced. An admin panel shows all
orders and lets you cancel orders (restoring stock) and edit inventory.

## What changed from the original blueprint

The blueprint files were inconsistent: the storefront used hardcoded PHP
arrays with **no sizes** and never wrote to the database, while `admin.php`
expected a full `orders` / `order_items` / `shoe_sizes` schema. Orders would
never have reached the admin panel and stock never moved. This version wires
the whole flow through one database:

- `index.php` / `cart.php` / `checkout.php` are now database-backed with
  per-size selection and live stock.
- `send_order.php` now saves the order, reduces stock by shoe **and** size
  inside a transaction (with row locking so sizes can't be oversold), then
  emails and clears the cart.
- `schema.sql` creates the `shoes`, `shoe_sizes`, `orders`, and `order_items`
  tables (column names match `admin.php`) and seeds 5 shoes in sizes 7–14.

## Local setup (XAMPP)

1. Start **Apache** and **MySQL** in XAMPP.
2. Copy this folder into `xampp/htdocs/` (e.g. `xampp/htdocs/sneaker-store/`).
3. In **phpMyAdmin**, click **Import** and load `schema.sql`
   (or run `mysql -u root < schema.sql`). This creates the `sneaker_store`
   database and all tables with sample data.
4. Add sneaker images to the `assets/` folder using the filenames in
   `schema.sql` (`airforce1.png`, `jordan1.png`, `samba.png`, `nb550.png`,
   `yeezy350.png`). Missing images automatically show a "No Image" placeholder.
5. Open <http://localhost/sneaker-store/index.php>.

## Admin

- URL: `admin_login.php`
- Username: `IR247`
- Password: `ADMIN123$`

> Note: these credentials are hardcoded in `admin_login.php` as the assignment
> requires. That's fine for a class demo but not safe for real production use.

## Deployment (Render)

Push to GitHub and deploy as a Docker web service. Set these environment
variables in the Render dashboard (Aiven or another hosted MySQL):

```
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
```

`db.php` reads these automatically and falls back to XAMPP defaults locally.
Import `schema.sql` into the hosted database once before first use.

## Email

`send_order.php` uses PHP `mail()`, which usually won't send on local XAMPP
without SMTP configured. The order still saves and the details are shown on
the confirmation page. Change the `$to` address in `send_order.php` to your own.
