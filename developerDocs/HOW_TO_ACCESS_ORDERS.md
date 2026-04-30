# How to Access Order Information in WooCommerce

## Overview

WooCommerce orders can be accessed through multiple methods: WordPress admin UI, WooCommerce functions, WP-CLI, and direct database queries.

---

## 1. WordPress Admin Interface

### View Orders in Admin

1. **Navigate to Orders:**
   - Go to `http://localhost:8080/wp-admin`
   - Click **WooCommerce → Orders**
   - You'll see a list of all orders with:
     - Order number
     - Customer name
     - Order date
     - Order status
     - Order total

2. **View Order Details:**
   - Click on any order number to see full details
   - Includes: billing/shipping info, order items, payment method, order notes

### Direct URL
- Orders List: `http://localhost:8080/wp-admin/edit.php?post_type=shop_order`
- Specific Order: `http://localhost:8080/wp-admin/post.php?post={ORDER_ID}&action=edit`

---

## 2. Using WooCommerce PHP Functions

### Get Orders Programmatically

```php
// Get all orders
$orders = wc_get_orders();

// Get orders with filters
$orders = wc_get_orders(array(
    'limit' => 10,
    'status' => 'completed',
    'orderby' => 'date',
    'order' => 'DESC',
));

// Get a specific order by ID
$order = wc_get_order(22);

// Access order properties
$order_id = $order->get_id();
$total = $order->get_total();
$status = $order->get_status();
$date = $order->get_date_created();
$customer_id = $order->get_customer_id();

// Get order items
$items = $order->get_items();
foreach ($items as $item) {
    $product_name = $item->get_name();
    $quantity = $item->get_quantity();
    $total = $item->get_total();
}
```

### In Your Plugin Code

The Dataviz AI plugin already uses this method in `class-dataviz-ai-data-fetcher.php`:

```php
public function get_recent_orders( array $args = array() ) {
    $defaults = array(
        'limit'   => 5,
        'orderby' => 'date',
        'order'   => 'DESC',
    );
    return wc_get_orders( wp_parse_args( $args, $defaults ) );
}
```

---

## 3. Using WP-CLI

### List Orders

```bash
# List all orders
docker compose exec wpcli wp post list --post_type=shop_order --allow-root

# List orders with details
docker compose exec wpcli wp eval '
$orders = wc_get_orders(array("limit" => 10));
foreach($orders as $order) {
    echo "Order #" . $order->get_id() . " - " . $order->get_status() . " - $" . $order->get_total() . "\n";
}
' --allow-root
```

### Get Order Details

```bash
# Get specific order details
docker compose exec wpcli wp eval '
$order = wc_get_order(22);
if($order) {
    echo "Order #" . $order->get_id() . "\n";
    echo "Status: " . $order->get_status() . "\n";
    echo "Total: $" . $order->get_total() . "\n";
    echo "Date: " . $order->get_date_created()->date("Y-m-d") . "\n";
}
' --allow-root
```

---

## 4. Direct Database Queries

### Query Orders from MariaDB

```sql
-- List all orders
SELECT 
    p.ID AS order_id,
    p.post_status AS status,
    p.post_date AS order_date,
    pm_total.meta_value AS total
FROM wp_posts p
LEFT JOIN wp_postmeta pm_total 
    ON p.ID = pm_total.post_id 
    AND pm_total.meta_key = '_order_total'
WHERE p.post_type = 'shop_order'
ORDER BY p.post_date DESC;

-- Get order items
SELECT 
    oi.order_id,
    oi.order_item_name,
    oim_qty.meta_value AS quantity,
    oim_total.meta_value AS line_total
FROM wp_woocommerce_order_items oi
LEFT JOIN wp_woocommerce_order_itemmeta oim_qty 
    ON oi.order_item_id = oim_qty.order_item_id 
    AND oim_qty.meta_key = '_qty'
LEFT JOIN wp_woocommerce_order_itemmeta oim_total 
    ON oi.order_item_id = oim_total.order_item_id 
    AND oim_total.meta_key = '_line_total'
WHERE oi.order_id = 22;
```

### Via WP-CLI Database Query

```bash
# Query orders from database
docker compose exec wpcli wp db query "
SELECT 
    p.ID,
    p.post_status,
    p.post_date,
    pm.meta_value as total
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
WHERE p.post_type = 'shop_order'
ORDER BY p.post_date DESC
LIMIT 10;
" --allow-root
```

---

## 5. Via phpMyAdmin

1. **Access phpMyAdmin:**
   - Visit `http://localhost:8081`
   - Login: Username `wp`, Password `wppass`
   - Select `wordpress` database

2. **Browse Orders:**
   - Click on `wp_posts` table
   - Filter: `post_type = 'shop_order'`
   - View order details in `wp_postmeta` table

---

## 6. In Your Dataviz AI Plugin

### Current Implementation

The plugin already accesses orders in several places:

1. **Data Fetcher** (`class-dataviz-ai-data-fetcher.php`):
   ```php
   $orders = wc_get_orders(array('limit' => 20));
   ```

2. **AJAX Handler** (`class-dataviz-ai-ajax-handler.php`):
   ```php
   $orders = $this->data_fetcher->get_recent_orders(array('limit' => 20));
   ```

### Add Order Display to Admin Page

You can add an orders section similar to the customer information:

```php
// In render_admin_page() method
$orders = $this->data_fetcher->get_recent_orders(array('limit' => 10));

// Display in a table
foreach ($orders as $order) {
    echo "Order #" . $order->get_id();
    echo " - " . $order->get_status();
    echo " - $" . $order->get_total();
    echo " - " . $order->get_date_created()->date('Y-m-d');
}
```

---

## 7. Order Status Values

Common WooCommerce order statuses:
- `wc-pending` - Payment pending
- `wc-processing` - Order received, being processed
- `wc-on-hold` - Awaiting payment
- `wc-completed` - Order fulfilled
- `wc-cancelled` - Cancelled
- `wc-refunded` - Refunded
- `wc-failed` - Payment failed

---

## 8. Useful Order Properties

```php
$order = wc_get_order($order_id);

// Basic Info
$order->get_id();
$order->get_status();
$order->get_date_created();
$order->get_total();
$order->get_currency();

// Customer Info
$order->get_customer_id();
$order->get_billing_email();
$order->get_billing_first_name();
$order->get_billing_last_name();
$order->get_billing_address_1();
$order->get_billing_city();
$order->get_billing_state();
$order->get_billing_country();

// Payment Info
$order->get_payment_method();
$order->get_payment_method_title();
$order->get_date_paid();

// Items
$items = $order->get_items();
foreach ($items as $item) {
    $item->get_name();
    $item->get_quantity();
    $item->get_total();
    $product = $item->get_product();
}
```

---

## Quick Reference Commands

### View Orders via WP-CLI
```bash
cd /Users/jaganbravo/woocomerce-plugin/docker
docker compose exec wpcli wp eval 'print_r(wc_get_orders(array("limit" => 5)));' --allow-root
```

### Count Orders
```bash
docker compose exec wpcli wp db query "SELECT COUNT(*) FROM wp_posts WHERE post_type='shop_order';" --allow-root
```

### Get Order Totals
```bash
docker compose exec wpcli wp db query "
SELECT 
    SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_revenue
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'shop_order' 
AND p.post_status = 'wc-completed'
AND pm.meta_key = '_order_total';
" --allow-root
```

---

**Note:** Always use WooCommerce functions (`wc_get_orders()`) when possible rather than direct database queries, as they handle data integrity and hooks properly.

