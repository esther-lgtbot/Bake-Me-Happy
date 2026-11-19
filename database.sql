CREATE DATABASE IF NOT EXISTS el3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE el3;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'seller', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seller_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    image VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY products_seller_id (seller_id),
    CONSTRAINT products_seller_fk FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT products_price_nonnegative CHECK (price >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'shipped', 'accepted', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    customer_phone VARCHAR(30) NULL,
    fulfillment_method ENUM('pickup', 'delivery') NULL,
    delivery_address TEXT NULL,
    payment_method ENUM('gcash', 'cod', 'bank_transfer', 'pay_in_store') NULL,
    receipt_requested_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY orders_user_id (user_id),
    CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(160) NOT NULL,
    qty INT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    custom_note TEXT NULL,
    PRIMARY KEY (id),
    KEY order_items_order_id (order_id),
    KEY order_items_product_id (product_id),
    CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB;
