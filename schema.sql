-- ============================================================
--  ShowtimeSneakers  -  Database schema + seed data
--  Import this in phpMyAdmin (or: mysql -u root < schema.sql)
-- ============================================================

CREATE DATABASE IF NOT EXISTS sneaker_store
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sneaker_store;

-- Drop in dependency order so re-imports are clean
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS shoe_sizes;
DROP TABLE IF EXISTS shoes;

-- ------------------------------------------------------------
--  shoes: one row per sneaker model
-- ------------------------------------------------------------
CREATE TABLE shoes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255)   NOT NULL,
    brand       VARCHAR(100)   DEFAULT NULL,
    price       DECIMAL(10,2)  NOT NULL,
    image       VARCHAR(255)   DEFAULT NULL,
    description TEXT           DEFAULT NULL
);

-- ------------------------------------------------------------
--  shoe_sizes: stock tracked per shoe + size (7 through 14)
-- ------------------------------------------------------------
CREATE TABLE shoe_sizes (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    shoe_id  INT NOT NULL,
    size     VARCHAR(10) NOT NULL,
    stock    INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_shoe_size (shoe_id, size),
    FOREIGN KEY (shoe_id) REFERENCES shoes(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
--  orders: one row per placed order
--  (column names match admin.php: customer_name, customer_email,
--   address, total, status, created_at)
-- ------------------------------------------------------------
CREATE TABLE orders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    customer_name  VARCHAR(255)  NOT NULL,
    customer_email VARCHAR(255)  NOT NULL,
    customer_phone VARCHAR(50)   DEFAULT NULL,
    address        TEXT          NOT NULL,
    total          DECIMAL(10,2) NOT NULL,
    status         VARCHAR(50)   NOT NULL DEFAULT 'Active',
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
--  order_items: the individual shoe+size lines inside an order
--  (shoe_name and subtotal stored so the admin panel and the
--   order history stay correct even if a shoe is later renamed)
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    order_id  INT NOT NULL,
    shoe_id   INT NOT NULL,
    shoe_name VARCHAR(255)  NOT NULL,
    size      VARCHAR(10)   NOT NULL,
    quantity  INT           NOT NULL,
    price     DECIMAL(10,2) NOT NULL,
    subtotal  DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ============================================================
--  Seed data
-- ============================================================
INSERT INTO shoes (id, name, brand, price, image, description) VALUES
(1, 'Nike Air Force 1',   'Nike',        115.00, 'assets/airforce1.png', 'The timeless all-white classic.'),
(2, 'Air Jordan 1 Retro', 'Jordan',      180.00, 'assets/jordan1.png',   'Iconic high-top silhouette.'),
(3, 'Adidas Samba OG',    'Adidas',      100.00, 'assets/samba.png',     'Retro terrace staple.'),
(4, 'New Balance 550',    'New Balance', 110.00, 'assets/nb550.png',     'Vintage basketball revival.'),
(5, 'Yeezy Boost 350',    'Adidas',      230.00, 'assets/yeezy350.png',  'Boost-cushioned street favourite.');

-- Sizes 7-14 for each shoe, with a mix of in-stock and sold-out sizes.
-- (shoe_id, size, stock)
INSERT INTO shoe_sizes (shoe_id, size, stock) VALUES
-- Nike Air Force 1
(1,'7',4),(1,'8',6),(1,'9',2),(1,'10',0),(1,'11',5),(1,'12',3),(1,'13',1),(1,'14',0),
-- Air Jordan 1 Retro
(2,'7',2),(2,'8',3),(2,'9',4),(2,'10',4),(2,'11',0),(2,'12',2),(2,'13',0),(2,'14',1),
-- Adidas Samba OG
(3,'7',5),(3,'8',5),(3,'9',3),(3,'10',6),(3,'11',2),(3,'12',0),(3,'13',4),(3,'14',2),
-- New Balance 550
(4,'7',1),(4,'8',0),(4,'9',2),(4,'10',3),(4,'11',0),(4,'12',1),(4,'13',0),(4,'14',0),
-- Yeezy Boost 350
(5,'7',0),(5,'8',2),(5,'9',3),(5,'10',1),(5,'11',2),(5,'12',0),(5,'13',1),(5,'14',0);
