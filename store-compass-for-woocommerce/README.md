# Unmai Analytix for WooCommerce (Sample)

Prototype plugin scaffold that demonstrates how the Unmai Analytix architecture fits into a WordPress + WooCommerce environment.

## Features

- Admin dashboard page that surfaces recent orders, top products, and customer metrics.
- Connection settings for storing the Unmai Analytix backend URL and API key.
- Quick analysis form that ships order data to the backend sample endpoint.
- Front-end `[unmai_analytix_chat]` shortcode for a lightweight AI chat widget (`[dataviz_ai_chat]` remains as a legacy alias).
- AJAX handlers that normalise WooCommerce data before sending to the backend.

## Getting Started

1. **Release / WordPress.org:** unzip so `wp-content/plugins/unmai-analytix-for-woocommerce/` contains the plugin (main file `unmai-analytix-for-woocommerce.php`). **From this repo:** the working tree lives in `store-compass-for-woocommerce/`; run `bash bin/sync-to-docker.sh` for Docker (destination folder is `unmai-analytix-for-woocommerce`).
2. Activate the **Unmai Analytix for WooCommerce** plugin in the WordPress admin.
3. Open **Unmai Analytix** in the WordPress sidebar (admin URL uses `page=unmai-analytix-for-woocommerce`).
4. Enter your backend API base URL and key, then save.
5. Use the quick analysis form or embed the `[unmai_analytix_chat]` shortcode on any page.

## Internal / legacy IDs (frozen)

Public name, text domain, menu slugs, shortcode, and API constants use **Unmai Analytix**. These historical identifiers are **kept on purpose** so stored data and existing scripts keep working:

- PHP classes `Dataviz_AI_*`, files `class-dataviz-ai-*.php`, `@package Dataviz_AI_WooCommerce`
- AJAX actions / nonces `dataviz_ai_*`
- Bootstrap helpers `dataviz_ai_wc_*` and constants `DATAVIZ_AI_WC_*`
- Database tables and options prefixed `dataviz_ai_*`
- Admin CSS hooks such as `.dataviz-ai-*` (the public chat widget also outputs `.unmai-analytix-*` classes)

API keys: prefer `UNMAI_ANALYTIX_API_KEY` / `UNMAI_ANALYTIX_API_BASE_URL`. `DATAVIZ_AI_API_KEY` and `DATAVIZ_AI_API_BASE_URL` still work.

## Development Notes

- The backend calls point to placeholder endpoints (`/api/woocommerce/ask`, `/api/chat`). Adjust these to match your API routes.
- Data sampling uses helper methods in `class-dataviz-ai-data-fetcher.php`. Extend these as your analytics grow.
- Scripts and styles live under `admin/` and `public/`. Replace the placeholder assets with your production UI.
- For localization, generate a `.pot` file into the `languages/` directory using `wp i18n make-pot`.

## License

GPL-2.0-or-later. See `LICENSE` if you plan to redistribute.
