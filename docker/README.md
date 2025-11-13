# Docker Quick Start (WordPress + WooCommerce)

Spin up a local WordPress site prepped for WooCommerce testing.

## Prerequisites

- Docker Desktop 4.0+ (macOS/Windows) or Docker Engine + Docker Compose v2 on Linux.
- Ports `8080` (WordPress) and `8081` (phpMyAdmin) available.

## 1. Start the stack

```bash
cd /Users/antonyprinston/Documents/sideProject/woocomerce-plugin/docker
docker compose up -d
```

First run pulls the images and mounts `./wordpress` as the document root. The WordPress installer lives at <http://localhost:8080>. Database admin sits at <http://localhost:8081> (user `wp`, password `wppass`).

## 2. Complete WordPress setup

1. Visit <http://localhost:8080>.
2. Choose language → site title → admin user/password (note them).
3. Log into `http://localhost:8080/wp-admin`.

## 3. Install WooCommerce

### Option A – WordPress Admin UI

1. `Plugins → Add New`.
2. Search for **WooCommerce** and click **Install**, then **Activate**.
3. Follow the onboarding wizard or skip optional steps.

### Option B – WP‑CLI

```bash
# open an interactive shell inside the wpcli container
docker compose exec wpcli bash

# once inside the container
wp plugin install woocommerce --activate
exit
```

## 4. Load sample store data (optional)

```bash
docker compose exec wpcli bash
curl -L -o /tmp/sample-products.csv https://raw.githubusercontent.com/woocommerce/woocommerce/trunk/sample-data/sample_products.csv
wp wc tool run install_pages
wp wc tool run install_default_tax_rates
wp wc tool run import_catalog -- --file=/tmp/sample-products.csv
exit
```

Alternatively, import `dummy-data.xml` via `Tools → Import → WordPress`.

## 5. Install the Dataviz AI sample plugin

```bash
cp -R /Users/antonyprinston/Documents/sideProject/woocomerce-plugin/dataviz-ai-woocommerce-plugin \
      /Users/antonyprinston/Documents/sideProject/woocomerce-plugin/docker/wordpress/wp-content/plugins/

docker compose exec wpcli bash -c "wp plugin activate dataviz-ai-woocommerce"
```

Refresh `wp-admin`; the **Dataviz AI** menu should appear.

## 6. Stop / teardown

- Stop services: `docker compose down`
- Clean volumes (removes DB contents): `docker compose down -v`

## Notes

- PHP files live under `docker/wordpress`. They persist across restarts.
- Change credentials in `docker-compose.yml` as needed.
- To run arbitrary WP‑CLI commands: `docker compose exec wpcli bash -c "wp <command>"`.

