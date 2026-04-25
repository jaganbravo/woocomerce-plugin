# WordPress.org submission — step-by-step

This file lives in **`developerDocs/`** (maintainer-only). It is not included in the plugin release ZIP (see `.distignore`). Customer-oriented guides such as `docs/API-KEY-MANAGEMENT.md` stay in **`docs/`**.

Use this checklist before uploading to the plugin directory or requesting review. Order matters: fix **blockers** first.

---

## Phase 1 — Security (required)

These items block a safe public release and will draw plugin-review feedback if skipped.

- [x] **Remove unauthenticated AI endpoints** *(implemented)*
  - [x] In `class-dataviz-ai-loader.php`, removed `wp_ajax_nopriv_dataviz_ai_analyze` and `wp_ajax_nopriv_dataviz_ai_chat`.

- [x] **Lock down the front-end chat shortcode** *(implemented)*
  - [x] `handle_chat_request()` requires **`manage_woocommerce`** after the nonce check.
  - [x] Shortcode output for guests / non–shop managers is a short message only (no nonce/API exposure in the HTML for them).

- [ ] **Re-test**
  - [ ] While logged out, confirm `admin-ajax.php` actions `dataviz_ai_analyze` and `dataviz_ai_chat` do not run your handlers (WordPress returns `0` for logged-out `wp_ajax_*` calls).
  - [ ] While logged in as **subscriber**, chat/analyze return **403** where applicable.

---

## Phase 2 — Repository & legal metadata

- [x] Add a **GPLv2 (or later)** file to the plugin root, e.g. `license.txt`, with the standard license text (same as `readme.txt` claims).
- [x] Replace **placeholder URLs** in `dataviz-ai-woocommerce.php`: `Plugin URI`, `Author URI` (no `example.com` for final submit).
- [ ] Set **`Contributors:`** in `readme.txt` to your **real** WordPress.org username(s) (comma-separated; must match an existing account, e.g. `https://profiles.wordpress.org/*yourname*/`). *Removed a bad placeholder (`datavizai`); add this line when you have registered on WordPress.org. Until then the readme omits the field; keep **`Author URI`** in the main plugin file pointed at a valid URL (currently the plugin’s directory URL — you may change `Author URI` to your profile if you prefer).*
- [x] Align **`Stable tag`** in `readme.txt` with the **`Version:`** header in the main plugin file.

---

## Phase 3 — Readme & honesty

- [x] Match **API key instructions** in `readme.txt` to real behavior (env vars, `config.php`, `wp-config.php` constants — **Settings/DB** documented only as *when a future build provides it*; see `docs/API-KEY-MANAGEMENT.md`).
- [x] In **Privacy / data**, state clearly that questions and **aggregates or limited result sets** may be sent to the AI provider; link to the provider’s privacy policy if required.
- [x] Document **external scripts**: Chart.js loaded from CDN (URL, version) — or bundle Chart.js in the plugin and update the readme to say “bundled”.

---

## Phase 4 — Hardening & directory hygiene

- [ ] Add empty **`index.php`** files in `includes/`, `admin/`, `admin/css/`, `admin/js/`, `public/`, etc., to reduce directory listing risk on misconfigured servers (WordPress convention).
- [x] **Debug AJAX:** `dataviz_ai_debug_intent` is **off** unless you add to `wp-config.php`: `define( 'DATAVIZ_AI_DEBUG_INTENT', true );`

---

## Phase 5 — Local validation

- [ ] Install the **[Plugin Check](https://wordpress.org/plugins/plugin-check/)** plugin on a staging site with your build; fix all **errors** and review **warnings**.
- [ ] Run **PHP** on your minimum supported version (readme says PHP 8.0).
- [ ] Activate on a clean site: **WordPress + WooCommerce only**; smoke-test chat, digests preview, uninstall (tables/options cleanup as intended).

---

## Phase 6 — WordPress.org assets

- [ ] Prepare **banner**: 1544×500 and 772×250 (PNG).
- [ ] Prepare **icon**: 256×256 and 128×128 (PNG).
- [ ] Capture **screenshots** (PNG) matching `readme.txt` **Screenshots** section; upload to SVN `assets/` (not inside the plugin ZIP).

---

## Phase 7 — Build & SVN

- [ ] Run your release script (e.g. `bin/build-release.sh`) and install the ZIP on a fresh site.
- [ ] Read **[How to use Subversion](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)** for plugins.
- [ ] Copy `readme.txt` and the main plugin file to **`/trunk`**; tag **`/tags/x.y.z/`** with the same version.
- [ ] Submit for **review** and respond promptly to feedback.

---

## Quick reference — files most often touched

| Concern | Where to look |
|--------|----------------|
| `nopriv` AJAX | `includes/class-dataviz-ai-loader.php` |
| Chat capability | `includes/class-dataviz-ai-ajax-handler.php` → `handle_chat_request()` |
| Shortcode | `includes/class-dataviz-ai-chat-widget.php` |
| Headers & version | `dataviz-ai-woocommerce.php` |
| Directory readme | `readme.txt` |
| Uninstall | `uninstall.php` |

---

## After approval

- [ ] Tag the release in Git.
- [ ] Keep **Tested up to** in `readme.txt` updated with new WordPress (and WooCommerce) versions as you verify compatibility.
