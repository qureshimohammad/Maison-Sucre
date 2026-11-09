# Maison Sucre - Patisserie Website (PHP + MySQL)

A 5-page online patisserie with registration, login, session authentication, cart, checkout and a printable bill.

**Stack:** PHP 7.4+ (PDO), MySQL/MariaDB, Bootstrap 5 (CDN), a little vanilla JavaScript.

## The 5 pages

| Page | File | What it does |
|------|------|--------------|
| Home | `index.php` | Hero, customer favourites, why-us, story |
| Menu | `menu.php` | Products from MySQL, category filter, add to cart |
| Login / Register | `auth.php` | Two tabs, validation, password hashing |
| Cart & Checkout | `checkout.php` | Edit cart, cake message, address, payment, place order (login required) |
| Bill | `bill.php` | Printable GST invoice + "My Orders" list (login required) |

Helper files: `db.php` (connection), `logout.php`, `includes/header.php`, `includes/footer.php`, `includes/functions.php`, `schema.sql`, `assets/`.

---

## Setup on Windows with XAMPP (recommended)

1. Install **XAMPP** (https://www.apachefriends.org) and open the XAMPP Control Panel.
2. Click **Start** next to **Apache** and **MySQL**.
3. Copy the whole `maison-sucre` folder into `C:\xampp\htdocs\`.
4. Open **http://localhost/phpmyadmin** in your browser.
5. Click the **Import** tab, choose `schema.sql`, scroll down and click **Import**.
   This creates the `maison_sucre` database, 4 tables and 12 sample products.
6. Open **http://localhost/maison-sucre/** and you are live.

`db.php` already uses the XAMPP defaults (user `root`, empty password). If your MySQL has a password, change `DB_PASS` in `db.php`.

### macOS / Linux
Install XAMPP/MAMP, or PHP + MariaDB yourself, then:
```
mysql -u root -p < schema.sql
php -S localhost:8000
```
and open http://localhost:8000.

---

## Test the full flow (2 minutes)

1. Open **Menu**, add a cake and a pastry to the cart.
2. Click **Cart / Checkout**. You are sent to login.
3. Use the **Register** tab to create an account, then log in.
4. In the cart type a message for the cake, click **Update cart**.
5. Enter an address and a 10-digit mobile number (starts with 6-9), pick a payment method and **Place order**.
6. The **Bill** page opens. Click **Print bill** (the navbar and buttons disappear in the print preview).
7. Open **My Orders** to see the order history. Check phpMyAdmin: stock has gone down, and `orders` and `order_items` have new rows.

---

## How it works (useful for your viva)

**Authentication**
- Registration validates every field, rejects duplicate emails, and stores `password_hash()` (bcrypt). Plain passwords are never saved.
- Login uses `password_verify()`, then `session_regenerate_id(true)` to prevent session fixation, then stores `user_id` in `$_SESSION`.
- `require_login()` guards `checkout.php` and `bill.php`; guests are redirected to `auth.php` and sent back after login.
- After 5 wrong passwords the login is blocked for 60 seconds.
- Logout is a POST with a CSRF token and destroys the session and cookie.

**Security checklist**
- SQL injection: every query uses PDO **prepared statements**.
- XSS: all output goes through `e()` (`htmlspecialchars`).
- CSRF: every form carries a random token checked by `csrf_verify()`.
- Prices and totals are always recalculated on the server from the database. The browser can never set a price.
- A customer can only open **their own** bills (`WHERE id = ? AND user_id = ?`).
- Redirect targets after login are whitelisted.

**Cart and checkout**
- The cart lives in `$_SESSION['cart']` as `product_id => [qty, cake message]`. No cart table is needed.
- Placing an order runs inside a **database transaction**: lock product rows (`SELECT ... FOR UPDATE`), check stock, insert into `orders`, insert into `order_items`, reduce stock, commit. If anything fails, everything is rolled back.
- `order_items` stores a copy of the product name and price, so old bills never change when the menu changes.

**Bill**
- Subtotal + GST 5% (shown as CGST 2.5% + SGST 2.5%) = grand total. Bill number looks like `MS00001`.
- Printing uses a `@media print` CSS rule that hides everything except the invoice.

## Database (4 tables)

```
users(id, name, email*, password_hash, phone, address, created_at)
products(id, name, category, description, price, emoji, badge, stock, is_featured)
orders(id, user_id -> users, subtotal, gst, total, payment_method, delivery_address, phone, status, created_at)
order_items(id, order_id -> orders, product_id -> products, product_name, quantity, unit_price, cake_message)
```

## Customising

- **Shop name, GST rate, categories, payment options:** constants at the top of `includes/functions.php`.
- **Address / GSTIN on the bill:** `bill.php` and `includes/footer.php` (they are sample values).
- **Add or edit products:** use phpMyAdmin (`products` table). Products use an `emoji` instead of photos to keep the project simple; swap in `<img>` tags if you want real photos.
- **Colours:** CSS variables at the top of `assets/css/style.css`.

## Troubleshooting

| Problem | Fix |
|---------|-----|
| "Could not connect to the database" | MySQL is not started in XAMPP, or `schema.sql` was not imported, or `db.php` credentials are wrong. |
| Blank page / PHP code shown as text | Open the site through `http://localhost/...`, not by double-clicking the file. |
| Emoji show as `????` | Re-import `schema.sql`; make sure the import character set is `utf-8`. |
| Styles look plain | The page loads Bootstrap and fonts from a CDN, so you need internet access. |
| Logged out immediately | Browser blocks cookies for `localhost`, or the PHP session folder is not writable. |

## Ideas to extend

Admin page to add products and update order status, PDF bill with Dompdf, product photos, order cancellation, email confirmation.
