# How to Create a WooCommerce Store Locally for Testing

## Prerequisites

- Docker containers running (wp_db, wp_app, wp_cli, wp_pma)
- WordPress accessible at `http://localhost:8080`

## Step-by-Step Setup

### Step 1: Complete WordPress Installation

1. **Visit WordPress Setup**
   - Open your browser: `http://localhost:8080`
   - You'll see the WordPress installation screen

2. **Configure WordPress**
   - Select your language
   - Enter site information:
     - **Site Title:** My WooCommerce Store
     - **Username:** admin (or your choice)
     - **Password:** (create a strong password - save it!)
     - **Email:** your-email@example.com
   - Click **Install WordPress**

3. **Log In**
   - After installation, log in with your credentials
   - You'll be taken to the WordPress dashboard

### Step 2: Install WooCommerce

#### Option A: Via WordPress Admin (Recommended)

1. **Navigate to Plugins**
   - In WordPress admin, go to **Plugins → Add New**

2. **Search for WooCommerce**
   - Type "WooCommerce" in the search box
   - Click **Install Now** on the official WooCommerce plugin
   - After installation, click **Activate**

3. **WooCommerce Setup Wizard**
   - You'll be redirected to the WooCommerce setup wizard
   - Follow the prompts:
     - **Store Details:**
       - Address, City, State, Country, Postal Code
       - Currency (e.g., USD)
     - **Industry:** Select relevant options
     - **Product Types:** Choose what you'll sell
     - **Business Details:** Revenue, employees, etc.
     - **Theme:** Choose a theme (Storefront is free and WooCommerce-optimized)
     - **Extensions:** Skip for now (you can add later)

#### Option B: Via WP-CLI (Command Line)

```bash
# Navigate to docker directory
cd /Users/jaganbravo/woocomerce-plugin/docker

# Install WooCommerce
docker compose exec wpcli wp plugin install woocommerce --activate --allow-root

# Run WooCommerce setup (creates default pages)
docker compose exec wpcli wp wc tool run install_pages --allow-root
```

### Step 3: Configure WooCommerce Settings

1. **Access WooCommerce Settings**
   - Go to **WooCommerce → Settings** in WordPress admin

2. **Configure General Settings**
   - **Store Address:** Your business address
   - **Selling Location:** Where you sell
   - **Currency:** Your store currency
   - **Currency Position:** Where currency symbol appears

3. **Configure Shipping** (Optional for testing)
   - Go to **WooCommerce → Settings → Shipping**
   - Add shipping zones and methods

4. **Configure Payments** (Optional for testing)
   - Go to **WooCommerce → Settings → Payments**
   - Enable **Cash on Delivery** or **Bank Transfer** for testing
   - (For real payments, you'll need payment gateway plugins)

### Step 4: Add Sample Products

#### Option A: Add Products Manually

1. **Create a Product**
   - Go to **Products → Add New**
   - Enter product details:
     - **Product Name:** Test Product 1
     - **Description:** Product description
     - **Regular Price:** $19.99
     - **Product Image:** Upload an image
   - Click **Publish**

2. **Repeat** for multiple test products

#### Option B: Import Sample Data (Faster)

1. **Use WooCommerce Sample Data**
   ```bash
   cd /Users/jaganbravo/woocomerce-plugin/docker
   
   # Download sample products CSV
   docker compose exec wpcli bash -c "curl -L -o /tmp/sample-products.csv https://raw.githubusercontent.com/woocommerce/woocommerce/trunk/sample-data/sample_products.csv"
   
   # Import products
   docker compose exec wpcli wp wc tool run import_catalog --file=/tmp/sample-products.csv --allow-root
   ```

2. **Or Use WordPress Importer**
   - Go to **Tools → Import → WordPress**
   - Install the importer if needed
   - Import a WooCommerce sample data XML file

#### Option C: Create Products via WP-CLI

```bash
cd /Users/jaganbravo/woocomerce-plugin/docker

# Create a simple product
docker compose exec wpcli wp wc product create \
  --name="Test Product" \
  --type=simple \
  --regular_price=29.99 \
  --status=publish \
  --allow-root

# Create a variable product
docker compose exec wpcli wp wc product create \
  --name="Variable Product" \
  --type=variable \
  --status=publish \
  --allow-root
```

### Step 5: Test Your Store

1. **View Your Store**
   - Visit `http://localhost:8080/shop` (if using Storefront theme)
   - Or visit your homepage and navigate to the shop

2. **Test Product Pages**
   - Click on a product to view details
   - Test adding to cart

3. **Test Checkout**
   - Add products to cart
   - Go to checkout
   - Test the checkout process (use test payment methods)

### Step 6: Install Your Dataviz AI Plugin

1. **Activate the Plugin**
   - Go to **Plugins → Installed Plugins**
   - Find **"Dataviz AI for WooCommerce"**
   - Click **Activate**

2. **Configure API Settings**
   - Go to **Dataviz AI → Settings**
   - Enter your API key (OpenAI or custom backend)
   - Leave API URL empty to use OpenAI directly
   - Click **Save Settings**

3. **Test the Plugin**
   - Go to **Dataviz AI** in the admin menu
   - Try asking: "What are the key trends from my recent orders?"
   - The plugin will analyze your WooCommerce data

## Quick Setup Script

Here's a complete setup script you can run:

```bash
#!/bin/bash
cd /Users/jaganbravo/woocomerce-plugin/docker

# Install WooCommerce
docker compose exec wpcli wp plugin install woocommerce --activate --allow-root

# Create default WooCommerce pages
docker compose exec wpcli wp wc tool run install_pages --allow-root

# Install default tax rates
docker compose exec wpcli wp wc tool run install_default_tax_rates --allow-root

# Create a test product
docker compose exec wpcli wp wc product create \
  --name="Sample Product" \
  --type=simple \
  --regular_price=19.99 \
  --description="This is a test product for WooCommerce" \
  --short_description="Test product" \
  --status=publish \
  --allow-root

echo "WooCommerce setup complete!"
echo "Visit http://localhost:8080/wp-admin to complete the setup wizard"
```

## Common Issues & Solutions

### Issue: Database Connection Error

**Solution:**
```bash
# Restart Docker containers
cd /Users/jaganbravo/woocomerce-plugin/docker
docker compose restart

# Check database is running
docker compose ps
```

### Issue: WordPress Not Installed

**Solution:**
- Visit `http://localhost:8080` in your browser
- Complete the WordPress installation wizard
- Or use WP-CLI:
```bash
docker compose exec wpcli wp core install \
  --url=http://localhost:8080 \
  --title="My WooCommerce Store" \
  --admin_user=admin \
  --admin_password=yourpassword \
  --admin_email=admin@example.com \
  --allow-root
```

### Issue: WooCommerce Pages Not Created

**Solution:**
```bash
docker compose exec wpcli wp wc tool run install_pages --allow-root
```

### Issue: Products Not Showing

**Solution:**
- Check products are published (not draft)
- Verify shop page exists: **WooCommerce → Settings → Products → Shop page**
- Clear any caching plugins

## Testing Checklist

- [ ] WordPress installed and accessible
- [ ] WooCommerce installed and activated
- [ ] WooCommerce setup wizard completed
- [ ] At least 3-5 test products created
- [ ] Shop page displays products
- [ ] Product pages work correctly
- [ ] Add to cart functionality works
- [ ] Checkout process works
- [ ] Dataviz AI plugin activated
- [ ] API key configured in plugin
- [ ] AI chat assistant working

## Next Steps

1. **Add More Products:** Create a variety of products for testing
2. **Configure Shipping:** Set up shipping zones and rates
3. **Test Orders:** Create test orders to generate data
4. **Test Plugin:** Use the Dataviz AI plugin to analyze store data
5. **Customize Theme:** Install and customize a WooCommerce-compatible theme

## Useful WP-CLI Commands

```bash
# List all products
docker compose exec wpcli wp wc product list --allow-root

# Get product details
docker compose exec wpcli wp wc product get <product_id> --allow-root

# Create an order
docker compose exec wpcli wp wc order create \
  --customer_id=1 \
  --payment_method="bacs" \
  --set_line_items[0][product_id]=1 \
  --set_line_items[0][quantity]=2 \
  --allow-root

# List orders
docker compose exec wpcli wp wc order list --allow-root

# Get WooCommerce status
docker compose exec wpcli wp wc status --allow-root
```

---

**Need Help?** Check the [WooCommerce Documentation](https://woocommerce.com/documentation/) or visit your local store at `http://localhost:8080`

