# Dataviz AI for WooCommerce (Sample)

Prototype plugin scaffold that demonstrates how the Dataviz AI architecture fits into a WordPress + WooCommerce environment.

## Features

- Admin dashboard page that surfaces recent orders, top products, and customer metrics.
- Connection settings for storing the Dataviz AI backend URL and API key.
- Quick analysis form that ships order data to the backend sample endpoint.
- Front-end `[dataviz_ai_chat]` shortcode for a lightweight AI chat widget.
- AJAX handlers that normalise WooCommerce data before sending to the backend.

## Getting Started

1. Copy the `dataviz-ai-woocommerce-plugin` folder into `wp-content/plugins/`.
2. Activate the **Dataviz AI for WooCommerce** plugin in the WordPress admin.
3. Open `Dataviz AI` in the WordPress sidebar.
4. Enter your backend API base URL and key, then save.
5. Use the quick analysis form or embed the `[dataviz_ai_chat]` shortcode on any page.

## Development Notes

- The backend calls point to placeholder endpoints (`/api/woocommerce/ask`, `/api/chat`). Adjust these to match your API routes.
- Data sampling uses helper methods in `class-dataviz-ai-data-fetcher.php`. Extend these as your analytics grow.
- Scripts and styles live under `admin/` and `public/`. Replace the placeholder assets with your production UI.
- For localization, generate a `.pot` file into the `languages/` directory using `wp i18n make-pot`.

## License

GPL-2.0-or-later. See `LICENSE` if you plan to redistribute.

