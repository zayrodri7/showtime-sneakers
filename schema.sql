-- ShowtimeSneakers database schema + seed data
-- Import this in phpMyAdmin (or run via MySQL CLI) if you prefer not to rely
-- on the auto-initializer in db.php.
--
--   mysql -u root -p sneaker_store < schema.sql

CREATE DATABASE IF NOT EXISTS sneaker_store
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sneaker_store;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS shoe_sizes;
DROP TABLE IF EXISTS shoes;

CREATE TABLE shoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    brand VARCHAR(80) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shoe_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shoe_id INT NOT NULL,
    size INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_shoe_size (shoe_id, size),
    FOREIGN KEY (shoe_id) REFERENCES shoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(50) DEFAULT NULL,
    address TEXT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    shoe_id INT NOT NULL,
    shoe_name VARCHAR(120) NOT NULL,
    size INT NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catalog
INSERT INTO shoes (id, name, brand, price, image, description) VALUES
(1, 'Nike Air Force 1',   'Nike',        115.00, 'assets/airforce1.png', 'The classic all-white court icon.'),
(2, 'Air Jordan 4 Retro', 'Jordan',      210.00, 'assets/jordan4.png',   'The Military Black colorway.'),
(3, 'Adidas Samba OG',    'Adidas',      100.00, 'assets/samba.png',     'Terrace-style everyday staple.'),
(4, 'New Balance 550',    'New Balance', 110.00, 'assets/nb550.png',     'Retro basketball silhouette.'),
(5, 'Yeezy Boost 350',    'Adidas',      230.00, 'assets/yeezy350.png',  'Boost-cushioned knit runner.');

-- Sizes 7-14 with sample stock. New Balance 550 is fully out of stock on purpose.
INSERT INTO shoe_sizes (shoe_id, size, stock) VALUES
(1,7,5),(1,8,6),(1,9,3),(1,10,0),(1,11,4),(1,12,2),(1,13,5),(1,14,1),
(2,7,3),(2,8,4),(2,9,2),(2,10,0),(2,11,3),(2,12,1),(2,13,2),(2,14,0),
(3,7,6),(3,8,5),(3,9,4),(3,10,0),(3,11,3),(3,12,2),(3,13,4),(3,14,3),
(4,7,0),(4,8,0),(4,9,0),(4,10,0),(4,11,0),(4,12,0),(4,13,0),(4,14,0),
(5,7,2),(5,8,3),(5,9,5),(5,10,0),(5,11,2),(5,12,1),(5,13,3),(5,14,2);
