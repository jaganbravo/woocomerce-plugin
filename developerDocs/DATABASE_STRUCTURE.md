# WooCommerce Database Structure in MariaDB

## Overview

All WooCommerce data (products, orders, customers) is automatically stored in **MariaDB** through WordPress. The database is running in the `wp_db` Docker container.

## Database Connection Details

- **Host:** `db:3306` (from WordPress container) or `localhost:3306` (from host)
- **Database Name:** `wordpress`
- **Username:** `wp`
- **Password:** `wppass`
- **Root Password:** `rootpass`

## Access Methods

### 1. phpMyAdmin (Web Interface)
- **URL:** `http://localhost:8081`
- **Username:** `wp`
- **Password:** `wppass`

### 2. WP-CLI (Command Line)
```bash
cd /Users/jaganbravo/woocomerce-plugin/docker
docker compose exec wpcli wp db query "YOUR_SQL_QUERY" --allow-root
```

### 3. Direct MySQL Client
```bash
docker compose exec db mysql -u wp -pwppass wordpress
```

## Key Database Tables

### Products Storage

**Main Table: `wp_posts`**
- Product posts are stored with `post_type = 'product'`
- `ID` - Product ID
- `post_title` - Product name
- `post_content` - Product description
- `post_status` - Product status (publish, draft, etc.)

**Product Meta: `wp_postmeta`**
- Stores product-specific data
- Key fields:
  - `_regular_price` - Regular price
  - `_sale_price` - Sale price
  - `_stock` - Stock quantity
  - `_stock_status` - Stock status
  - `_sku` - SKU
  - `_weight` - Product weight
  - `_length`, `_width`, `_height` - Dimensions

### Orders Storage

**Main Table: `wp_posts`**
- Orders are stored with `post_type = 'shop_order'`
- `ID` - Order ID
- `post_status` - Order status (wc-completed, wc-processing, wc-pending, etc.)
- `post_date` - Order date

**Order Meta: `wp_postmeta`**
- Stores order-specific data
- Key fields:
  - `_order_total` - Order total
  - `_order_currency` - Currency
  - `_customer_user` - Customer ID
  - `_payment_method` - Payment method
  - `_billing_email` - Billing email
  - `_shipping_total` - Shipping cost

**Order Items: `wp_woocommerce_order_items`**
- Stores line items in orders
- Links to `wp_woocommerce_order_itemmeta` for item details

### Customers Storage

**Main Table: `wp_users`**
- WordPress user accounts
- `ID` - User ID
- `user_email` - Email address
- `user_registered` - Registration date

**Customer Meta: `wp_usermeta`**
- Stores customer-specific data
- Key fields:
  - `billing_first_name`, `billing_last_name`
  - `billing_address_1`, `billing_city`, `billing_country`
  - `shipping_*` - Shipping information

## Current Data Summary

### Products
- **Total Products:** 10 Dataviz products
- **Price Range:** $79.99 - $249.99
- **All Status:** Published

### Orders
- **Total Orders:** 10 sample orders
- **Order Statuses:**
  - Completed: 8 orders
  - Processing: 1 order
  - Pending: 1 order
- **Total Revenue:** ~$2,389.81

## Useful SQL Queries

### List All Products
```sql
SELECT 
    p.ID,
    p.post_title AS product_name,
    pm_price.meta_value AS price,
    pm_stock.meta_value AS stock
FROM wp_posts p
LEFT JOIN wp_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_regular_price'
LEFT JOIN wp_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
WHERE p.post_type = 'product' 
AND p.post_status = 'publish'
ORDER BY p.ID;
```

### List All Orders
```sql
SELECT 
    p.ID AS order_id,
    p.post_status AS status,
    p.post_date AS order_date,
    pm_total.meta_value AS total
FROM wp_posts p
LEFT JOIN wp_postmeta pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
WHERE p.post_type = 'shop_order'
ORDER BY p.post_date DESC;
```

### Get Order Items
```sql
SELECT 
    oi.order_id,
    oi.order_item_name,
    oim.meta_value AS quantity
FROM wp_woocommerce_order_items oi
LEFT JOIN wp_woocommerce_order_itemmeta oim 
    ON oi.order_item_id = oim.order_item_id 
    AND oim.meta_key = '_qty'
ORDER BY oi.order_id;
```

### Product Sales Summary
```sql
SELECT 
    p.post_title AS product_name,
    SUM(oim_qty.meta_value) AS total_sold,
    SUM(oim_total.meta_value) AS total_revenue
FROM wp_posts p
INNER JOIN wp_woocommerce_order_items oi ON oi.order_item_name = p.post_title
INNER JOIN wp_woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
INNER JOIN wp_woocommerce_order_itemmeta oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
WHERE p.post_type = 'product'
GROUP BY p.ID, p.post_title
ORDER BY total_sold DESC;
```

## Database Backup

### Create Backup
```bash
cd /Users/jaganbravo/woocomerce-plugin/docker
docker compose exec db mysqldump -u wp -pwppass wordpress > backup.sql
```

### Restore Backup
```bash
docker compose exec -T db mysql -u wp -pwppass wordpress < backup.sql
```

## Data Persistence

The database data is stored in a Docker volume (`db_data`) which persists even when containers are stopped. Data is only lost if you run:
```bash
docker compose down -v  # Removes volumes
```

## Access via phpMyAdmin

1. Visit `http://localhost:8081`
2. Login with:
   - Server: `db`
   - Username: `wp`
   - Password: `wppass`
3. Select `wordpress` database
4. Browse tables and run queries

## Notes

- All products and orders are automatically stored in MariaDB
- WooCommerce uses WordPress's database structure
- Data is persistent across container restarts
- Use WordPress/WooCommerce functions to access data (don't query directly unless necessary)

---

**Last Updated:** November 15, 2025

