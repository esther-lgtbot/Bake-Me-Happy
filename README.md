# Bake Me Happy

A PHP/MySQL storefront for XAMPP with customer accounts, seller inventory, cart checkout, order history, and a JSON product catalog.

## XAMPP setup

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Open phpMyAdmin, select **Import**, and import `database.sql`.
3. Keep the default local connection (`localhost`, database `el3`, user `root`, empty password), or set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the PHP environment.
4. Open `http://localhost/EL3/`.
5. Register a customer account. To enable seller features or admin access, use phpMyAdmin to promote a trusted account:

```sql
UPDATE users SET role = 'seller' WHERE email = 'seller@example.com';
UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
```

Registration never accepts a role from the browser. Passwords are stored with PHP `password_hash()` and checked with `password_verify()`.

The `uploads` directory must be writable by Apache for product image uploads. Images are limited to 5 MB and JPG, PNG, WEBP, or GIF formats.

All PHP routes use the shared connection in `config/db.php`. If the site reports a database connection failure, start MySQL in the XAMPP Control Panel and verify that the configured database name matches the database containing your data. Connection details are written to the PHP error log; they are not shown to visitors.

## JSON catalog

`GET /EL3/api/products.php` returns a JSON object with product records under `data`. Product mutations and checkout use regular HTML forms, PDO prepared statements, session authentication, CSRF checks, and MySQL transactions.

## Existing installations

`database.sql` defines the expected schema for a new installation. It uses `CREATE DATABASE/TABLE IF NOT EXISTS`; it does not erase existing records, restore missing records, or migrate/rename columns in existing tables. Back up existing data and compare its tables before importing. Product deletion in the seller inventory permanently removes that product; deleting a seller user directly in MySQL also cascades deletion to that seller's products.

If checkout reports an unknown `product_name` column in `order_items`, update that existing table and preserve names for existing orders with:

```sql
ALTER TABLE order_items
    ADD COLUMN product_name VARCHAR(160) NOT NULL DEFAULT '' AFTER product_id;

UPDATE order_items oi
JOIN products p ON p.id = oi.product_id
SET oi.product_name = p.name
WHERE oi.product_name = '';
```

To enable customer customization requests on an existing installation, add the order-item note column:

```sql
ALTER TABLE order_items
    ADD COLUMN custom_note TEXT NULL AFTER price;
```

To enable customer contact, fulfillment, and payment details on existing orders, add these nullable columns:

```sql
ALTER TABLE orders
    ADD COLUMN customer_phone VARCHAR(30) NULL AFTER status,
    ADD COLUMN fulfillment_method ENUM('pickup', 'delivery') NULL AFTER customer_phone,
    ADD COLUMN delivery_address TEXT NULL AFTER fulfillment_method,
    ADD COLUMN payment_method ENUM('gcash', 'cod', 'bank_transfer', 'pay_in_store') NULL AFTER delivery_address;
```

To enable customer receipt requests on an existing installation, add:

```sql
ALTER TABLE orders
    ADD COLUMN receipt_requested_at TIMESTAMP NULL DEFAULT NULL AFTER payment_method;
```

To enable seller order progress statuses on an existing installation, update the `orders.status` enum while preserving existing and legacy values:

```sql
ALTER TABLE orders
    MODIFY COLUMN status ENUM(
        'pending', 'paid', 'shipped', 'accepted', 'preparing',
        'ready_for_pickup', 'out_for_delivery', 'completed', 'cancelled'
    ) NOT NULL DEFAULT 'pending';
```
