-- ============================================================
--  Maison Sucre - database schema + sample data
--  Import in phpMyAdmin (Import tab) or run:  mysql -u root -p < schema.sql
--  WARNING: re-importing drops and recreates the tables (data is lost).
-- ============================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS maison_sucre CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maison_sucre;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

-- ---------- users ----------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          -- bcrypt hash from password_hash()
  phone         VARCHAR(15)  NOT NULL,
  address       TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- products ----------
CREATE TABLE products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  category    VARCHAR(30)  NOT NULL,            -- Cakes, Pastries, Macarons, Cookies, Specials
  description VARCHAR(255) NOT NULL,
  price       DECIMAL(8,2) NOT NULL,
  emoji       VARCHAR(16)  NOT NULL DEFAULT '🧁',  -- used instead of photos to keep things simple
  image       VARCHAR(100) NULL,
  badge       VARCHAR(30)  NULL,                -- e.g. "Bestseller", "Diwali Special"
  stock       INT NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_category (category)
) ENGINE=InnoDB;

-- ---------- orders ----------
CREATE TABLE orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  subtotal         DECIMAL(10,2) NOT NULL,
  gst              DECIMAL(10,2) NOT NULL,
  total            DECIMAL(10,2) NOT NULL,
  payment_method   ENUM('COD','UPI','Card') NOT NULL DEFAULT 'COD',
  delivery_address TEXT NOT NULL,
  phone            VARCHAR(15) NOT NULL,
  status           ENUM('Placed','Preparing','Delivered','Cancelled') NOT NULL DEFAULT 'Placed',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------- order_items ----------
-- product_name and unit_price are copied at order time so old bills never change
-- even if the menu is edited later.
CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  product_name VARCHAR(120) NOT NULL,
  quantity     INT UNSIGNED NOT NULL,
  unit_price   DECIMAL(8,2) NOT NULL,
  cake_message VARCHAR(60) NULL,
  CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------- sample menu ----------
INSERT INTO products (name, category, description, price, emoji, badge, stock, is_featured) VALUES
('Belgian Truffle Cake',      'Cakes',    'Dark Belgian chocolate sponge layered with silky truffle ganache.',        799.00, '🎂', 'Bestseller',     10, 1),
('Red Velvet Cake',           'Cakes',    'Velvety red sponge with tangy cream cheese frosting.',                     749.00, '🍰', NULL,             8,  0),
('Fresh Fruit Gateau',        'Cakes',    'Light vanilla sponge, whipped cream and seasonal fruit.',                  699.00, '🍓', NULL,             6,  0),
('Butter Croissant',          'Pastries', 'Flaky, golden and laminated with French-style butter. Baked every morning.', 90.00, '🥐', 'Fresh today',    40, 1),
('Pain au Chocolat',          'Pastries', 'Buttery croissant dough wrapped around two bars of dark chocolate.',       120.00, '🥐', NULL,             30, 0),
('Blueberry Danish',          'Pastries', 'Crisp pastry, vanilla custard and a heap of blueberry compote.',           110.00, '🥧', NULL,             25, 0),
('Chocolate Eclair',          'Pastries', 'Choux pastry filled with chocolate cream and glazed on top.',              130.00, '🍫', NULL,             20, 0),
('Rose Macarons (box of 6)',  'Macarons', 'Delicate almond shells with rose buttercream. A lovely gift.',             540.00, '🌹', 'Gift favourite', 15, 1),
('Pistachio Macarons (box of 6)', 'Macarons', 'Crisp outside, chewy inside, filled with roasted pistachio cream.',    560.00, '🌿', NULL,             12, 0),
('Choco Chip Cookies (pack of 4)', 'Cookies', 'Big, soft-centred cookies loaded with dark chocolate chunks.',         220.00, '🍪', NULL,             35, 1),
('Oatmeal Raisin Cookies (pack of 4)', 'Cookies', 'Chewy oats, plump raisins and a hint of cinnamon.',                 200.00, '🍪', NULL,             30, 0),
('Diwali Celebration Box',    'Specials', 'Assorted mini cakes, cookies and macarons in a festive gift box.',         999.00, '🪔', 'Diwali Special', 10, 0);
