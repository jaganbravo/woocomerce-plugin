=== Dataviz AI for WooCommerce ===
Tags: woocommerce, analytics, artificial intelligence, chat, reports, email
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask questions about your WooCommerce store in plain English. Get answers, charts, and scheduled email digests powered by AI.

== Description ==

**Dataviz AI for WooCommerce** brings a conversational analytics experience to your store admin. Ask about orders, revenue, products, customers, inventory, coupons, and more — without building custom reports.

= Key features =

* **Natural language chat** in WordPress admin — query your store data with everyday questions.
* **Hybrid architecture** — structured intent parsing plus PHP execution for reliable, deterministic answers where it matters.
* **Charts** when you explicitly ask for them (pie, bar, line) using a backend chart descriptor — no fragile keyword guessing on the client.
* **Scheduled email digests** — automated summaries (revenue, top categories, low stock, etc.) on a schedule you choose, with an HTML preview in admin.
* **Support & feature requests** — capture failed or unsupported questions for review in a unified admin list.

= Documentation =

* **WordPress.org plugin page** — Shows this **readme** (Description, Installation, FAQ). Most users never need to open other files.
* **After install** — Optional deep guides ship inside the plugin ZIP under the `docs/` folder (same path on your server: `wp-content/plugins/…/dataviz-ai-woocommerce-plugin/docs/`). For example, `API-KEY-MANAGEMENT.md` explains API key precedence, roles, and security in one place. Open with a text editor, hosting file manager, or SFTP. (GitHub or another public repo may also host the same files for easy reading in the browser.)
* You do **not** need to read every `.md` file to use the plugin — start with **Installation** and **FAQ** below.

= Requirements =

* WordPress 6.0 or newer
* WooCommerce active
* PHP 8.0 or newer
* An OpenAI-compatible API key, configured in one of the ways listed under **Installation** and **Where do I set the API key?** (see `docs/API-KEY-MANAGEMENT.md` for the full order of precedence).

= Privacy & data =

Store data is processed on your server to run WooCommerce queries. Requests to the AI provider include your question and retrieved aggregates or limited result sets — not your full database. If you use OpenAI, read their current policies: https://openai.com/policies/ (including the Privacy Policy). Configure API keys securely; do not commit `config.php` to version control.

= External scripts =

* **Chart.js** (MIT License) is enqueued in the WordPress **admin** on the Dataviz AI chat screen to draw charts. It is loaded from the **jsDelivr** CDN: `https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js` (**Chart.js 4.4.4**). It is not loaded on the public storefront by default. Project site: https://www.chartjs.org/

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/dataviz-ai-woocommerce-plugin/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Ensure **WooCommerce** is installed and active.
4. **Configure your API key** — same precedence as the FAQ below (env → `DATAVIZ_AI_API_KEY` in `wp-config.php` / `config.php` → optional future Settings/DB). Details: `docs/API-KEY-MANAGEMENT.md`.
5. Open **Dataviz AI** from the admin menu and start chatting.

== Frequently Asked Questions ==

= Does this work without WooCommerce? =

No. WooCommerce must be installed and active.

= Where do I set the API key? =

**Precedence (first match wins):** (1) **Environment** — `OPENAI_API_KEY` or `DATAVIZ_AI_API_KEY` (optional `DATAVIZ_AI_API_BASE_URL`). (2) **Constant** — `define( 'DATAVIZ_AI_API_KEY', '...' );` in `wp-config.php` *or* `config.php` (from `config.php.example`). (3) **Database** — only if your installed version provides a **Settings** screen; ignored when (1) or (2) is set. Full walkthrough: `docs/API-KEY-MANAGEMENT.md` inside the plugin package (path on server: `wp-content/plugins/…/docs/`).

= Why don’t I receive digest emails? =

WordPress uses `wp_mail()`. On local Docker or some hosts, PHP `mail()` is not configured. Install an SMTP plugin (e.g. WP Mail SMTP) and use a real SMTP provider. See `docs/EMAIL-DIGESTS.md` in the plugin package.

= How is chat history stored? =

Messages are stored in a custom database table for the logged-in user, with automatic cleanup of older rows. Uninstalling the plugin removes plugin tables and settings (see uninstall handler).

== Screenshots ==

1. Chat interface in admin — ask questions about your store.
2. Example answer with a chart when you request a visualization.
3. Email Digests — schedule and preview HTML digests.
4. Support requests list for reviewing failed or feature requests.

== Changelog ==

= 1.0.0 =
* Initial public release.
* AI chat with intent pipeline, tool execution, and answer composition.
* Backend chart descriptors for reliable Chart.js rendering.
* Email digests with preview, Send Now, and WP-Cron processing.
* Unified support / feature request storage and admin UI.

== Upgrade Notice ==

= 1.0.0 =
First stable release. Configure API keys before use.
