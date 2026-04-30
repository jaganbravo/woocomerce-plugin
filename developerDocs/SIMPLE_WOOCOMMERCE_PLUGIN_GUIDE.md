# Simple WooCommerce Plugin Guide

This guide walks you through setting up a local WooCommerce environment and building a minimal plugin that reads sample store data.

---

## 1. Set Up Your Local Environment

1. **Install WordPress locally**
   - Use tools like Local (by Flywheel), DevKinsta, MAMP, or Docker.
   - Example Docker command:
     ```bash
     docker run -d --name wp \
       -p 8080:80 \
       -e WORDPRESS_DB_HOST=db \
       -e WORDPRESS_DB_USER=wp \
       -e WORDPRESS_DB_PASSWORD=secret \
       -e WORDPRESS_DB_NAME=wp \
       -v "$PWD/wordpress":/var/www/html \
       wordpress:latest
     ```

2. **Install WooCommerce**
   - From the WP dashboard: `Plugins → Add New → WooCommerce`.
   - Run the onboarding wizard (feel free to skip non-essential steps).

3. **Import sample data**
   - WooCommerce dashboard: `Tools → Import → WooCommerce Products (CSV/XML)`.
   - Alternatively, import the `dummy-data.xml` from GitHub: <https://github.com/woocommerce/woocommerce/tree/trunk/sample-data>.
   - After import, you will have demo products, orders, and customers to experiment with.

---

## 2. Create a Starter Plugin

1. **Create plugin folder**
   - Path: `wp-content/plugins/sample-woocommerce-plugin/`.

2. **Add plugin bootstrap file**
   ```php
   <?php
   /**
    * Plugin Name: Sample WooCommerce Plugin
    * Description: Example plugin that reads WooCommerce data.
    * Version: 0.1.0
    * Author: Your Name
    */

   if (!defined('ABSPATH')) exit; // Exit if accessed directly

   class Sample_Woo_Plugin {
       public function __construct() {
           add_action('plugins_loaded', array($this, 'init'), 20);
       }

       public function init() {
           if (!class_exists('WooCommerce')) {
               add_action('admin_notices', function() {
                   echo '<div class="notice notice-error"><p>WooCommerce is required for Sample WooCommerce Plugin.</p></div>';
               });
               return;
           }

           add_action('admin_menu', array($this, 'register_menu'));
       }

       public function register_menu() {
           add_menu_page(
               'Sample Woo Stats',
               'Sample Woo',
               'manage_woocommerce',
               'sample-woo-plugin',
               array($this, 'render_admin_page'),
               'dashicons-chart-area',
               56
           );
       }

       public function render_admin_page() {
           $orders = wc_get_orders(array(
               'limit' => 5,
               'orderby' => 'date',
               'order' => 'DESC',
           ));

           echo '<div class="wrap"><h1>Sample WooCommerce Data</h1>';
           echo '<p>Showing the 5 most recent orders:</p>';
           echo '<ul>';

           foreach ($orders as $order) {
               echo '<li>Order #' . $order->get_id() . ' — ' .
                    wc_price($order->get_total()) . ' — ' .
                    $order->get_date_created()->date('Y-m-d') . '</li>';
           }

           echo '</ul></div>';
       }
   }

   new Sample_Woo_Plugin();
   ```

3. **Activate plugin**
   - In WordPress: `Plugins → Sample WooCommerce Plugin → Activate`.
   - A new “Sample Woo” menu will appear with recent order data.

---

## 3. Extend the Plugin

- Replace `wc_get_orders()` with other WooCommerce helpers:
  - `wc_get_products()` for catalog details.
  - `get_users(['role' => 'customer'])` for customer insights.
- Add filters (date ranges, product categories).
- Render charts using a JS library (Chart.js, ApexCharts).
- Expose a `WP REST API` endpoint with `register_rest_route()`.

---

## 4. Testing With Sample Data

- Use WooCommerce’s sample factories for automated tests.
- You can also create test orders programmatically with `wc_create_order()`.
- Keep sample data fixtures checked into your repo for deterministic tests.

---

## 5. Helpful References

- WordPress Plugin Handbook: <https://developer.wordpress.org/plugins/>
- WooCommerce developer docs: <https://developer.woocommerce.com/>
- WP-CLI scaffold command: `wp scaffold plugin sample-woocommerce-plugin --plugin-name="Sample WooCommerce Plugin"`

---

With this skeleton you can experiment freely—add analytics, export data to your AI backend, or integrate the architecture we designed earlier. Let me know if you want a scaffold script or test harness next.
