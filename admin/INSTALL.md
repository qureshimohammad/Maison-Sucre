# Maison Sucre – Admin area

Everything lives in this one `admin/` folder. No existing file is modified.

## Install (2 steps)

1. Copy the `admin` folder into your project, next to `index.php`:
   ```
   maison-sucre/
     admin/        <-- new
     assets/
     includes/
     index.php ...
   ```
2. Create your admin account. In a terminal / XAMPP Shell:
   ```
   cd C:\xampp\htdocs\maison-sucre\admin
   C:\xampp\php\php.exe setup_admin.php
   ```
   It creates the `admins` table automatically and asks for your name, email and password.
   (Prefer phpMyAdmin? Import `sql/admin_schema.sql`, then still run the script to add the account.)

Then open **http://localhost/maison-sucre/admin/login.php**.

To reset a forgotten password, run `setup_admin.php` again with the same email.

## What you get

| Page | File | What it does |
|------|------|--------------|
| Login / Logout | `login.php`, `logout.php` | Server-side session login, separate from customer login |
| Dashboard | `index.php` | Today's orders and revenue, status counts, low-stock list, recent orders |
| Orders | `orders.php`, `order.php` | Search + filter, full order view, change status (Placed → Preparing → Delivered / Cancelled) |
| Products | `products.php`, `product_edit.php` | Add, edit, delete, quick stock edit, featured toggle, photo upload |
| Customers | `customers.php` | Read-only list with order count and total spent |

## How it avoids colliding with the shop

- Own `admins` table. Customers (`users`) can never become admins.
- Own session: cookie `MS_ADMIN`, limited to the `/admin` path. Logging in as admin does not log you in as a customer and vice versa.
- Does not include `includes/functions.php` / `header.php`; all helpers are prefixed `a_` / `A_`. Only `../db.php` is reused.
- `schema.sql` re-imports do not drop `admins`.

## Security notes (good for your viva)

- Passwords: `password_hash()` / `password_verify()`; the login also runs a dummy verify for unknown emails so timing does not reveal which emails are admins.
- `session_regenerate_id(true)` on login; session cookie is HttpOnly + SameSite=Lax; auto logout after 30 minutes idle.
- Brute force: 5 wrong passwords lock that account for 5 minutes (stored in the database, so clearing cookies does not bypass it).
- CSRF token on every POST form; logout is POST only.
- All queries use PDO prepared statements; all output is escaped with `a_e()`.
- Cancelling an order runs in a transaction with row locking and puts the stock back exactly once. Delivered/Cancelled orders are locked.
- Photo upload checks the real file type (finfo + getimagesize), 2 MB limit, random file name; only files the admin panel created (`p_*.jpg/png/webp`) are ever deleted.
- `setup_admin.php` refuses to run in a browser. `includes/` and `sql/` are blocked by `.htaccess` on Apache.

## Things to know

- Categories are listed in `A_CATEGORIES` at the top of `includes/bootstrap.php`. Keep it the same as `CATEGORIES` in your `includes/functions.php`.
- A product used in past orders cannot be deleted (bills must keep working); it is set to stock 0 instead.
- Over real HTTPS hosting nothing needs changing; the session cookie automatically becomes `Secure`.
