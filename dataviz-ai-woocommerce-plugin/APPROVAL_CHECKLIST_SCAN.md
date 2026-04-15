# WooCommerce / WordPress plugin approval checklist — Dataviz AI scan

This document records a **static code review** against common WordPress.org, WooCommerce, and marketplace-style expectations. Re-run checks after major changes.

**Scan date:** 2026-03-30  
**Scope:** `dataviz-ai-woocommerce-plugin/` (main plugin tree)

---

## 1. Reference checklist (what reviewers expect)

### Technical & WordPress standards

- [ ] Plugin header: `Plugin Name`, `Version`, `Description`, `Author`, `Plugin URI`, `Requires at least`, `Requires PHP`, `WC requires at least`, `Text Domain`, `Domain Path`, `License` / `License URI` where applicable.
- [ ] No fatals on supported WP/PHP/WC versions; safe activation/deactivation.
- [ ] WooCommerce active before using WC APIs; clear admin notice if missing.
- [ ] Privileged actions: `current_user_can( … )` as appropriate (not only `is_admin()`).
- [ ] AJAX: `check_ajax_referer` / nonces + capability checks for sensitive operations.
- [ ] Sanitize input, escape output; `$wpdb->prepare()` for dynamic SQL.
- [ ] API keys never exposed in JavaScript or public HTML; not committed in VCS.
- [ ] `load_plugin_textdomain` on `init` (WordPress 6.7+ avoids “too early” translation notices).
- [ ] `uninstall.php` removes plugin-owned data (tables, options, cron, user meta) or documents exceptions.

### WooCommerce-specific

- [ ] Prefer WC APIs (`wc_get_orders`, CRUD, hooks) over ad-hoc SQL where possible.
- [ ] Document read vs write behavior; avoid touching orders/checkout unless intended.
- [ ] Performance: bounded queries on large stores.
- [ ] **HPOS:** declare compatibility and test with High-Performance Order Storage enabled.

### Privacy & compliance

- [ ] Disclose external services (e.g. OpenAI): data sent, purpose, links to policies.
- [ ] If storing personal data: consider Privacy Policy suggested text, exporters, erasers (`wp_privacy_*` / personal data API).

### Packaging

- [ ] `readme.txt` for WordPress.org (if submitting there).
- [ ] Screenshots, stable tag, changelog alignment with `Version`.

---

## 2. Scan results — satisfied

| Item | Notes |
|------|--------|
| WooCommerce required | Activation checks `class_exists( 'WooCommerce' )` and fails cleanly; runtime admin notice if inactive (`dataviz-ai-woocommerce.php`). |
| HPOS declared | `FeaturesUtil::declare_compatibility( 'custom_order_tables', … )` on `before_woocommerce_init`. |
| Admin AJAX: nonce + capability | e.g. `handle_analysis_request()`: `check_ajax_referer( 'dataviz_ai_admin', 'nonce' )` and `current_user_can( 'manage_woocommerce' )` (`class-dataviz-ai-ajax-handler.php`). Similar pattern on history, feature request, inventory, debug intent. |
| Input sanitization (admin paths) | `sanitize_text_field` / `sanitize_textarea_field` with `wp_unslash` on request data. |
| API key not in frontend JS | `wp_localize_script` exposes booleans like `hasApiKey` / `connected`, not the secret (`class-dataviz-ai-admin.php`, onboarding, chat widget). |
| API key resolution | Env vars → `config.php` constant → option fallback (`class-dataviz-ai-api-client.php`). |
| SQL hygiene | Table names use `$wpdb->prefix`; many dynamic queries use `prepare()`. |

---

## 3. Scan results — partial / risk

| Item | Notes |
|------|--------|
| Plugin header / metadata | Placeholder URIs, sample description, `Version` may not match release; add `License` / `License URI` if targeting directories. |
| Translations | `load_plugin_textdomain()` runs inside code hooked to `plugins_loaded`. On WordPress 6.7+, prefer loading the text domain on `init` to avoid “translations loaded too early” notices. |
| API key in options | `get_api_key()` still falls back to `dataviz_ai_wc_settings['api_key']`. Stricter posture: env/constants only, with migration off options. |
| WordPress.org readme | No `readme.txt` in plugin root (needed for wordpress.org). |
| Local `config.php` | Listed in `.gitignore` (good). Never commit real keys. |

---

## 4. Scan results — not satisfied (fix before strict approval)

| Priority | Item | Notes |
|----------|------|--------|
| **High** | Public chat AJAX authorization | `handle_chat_request()` checks `dataviz_ai_chat` nonce but **not** `current_user_can( 'manage_woocommerce' )`. The `[dataviz_ai_chat]` shortcode exposes a nonce to page visitors; combined with order context in `get_recent_orders`, this is a **data exposure** risk. **Fix:** require shop-manager capability (or remove/disable public shortcode until gated). |
| **High** | Uninstall completeness | `uninstall.php` drops `dataviz_ai_chat_history` and `dataviz_ai_feature_requests` and some user meta, but activation also creates **`dataviz_ai_support_requests`** and **`dataviz_ai_email_digests`**. Those tables are not dropped. Clear related cron events (e.g. digest processing) on uninstall. |
| **Medium** | Privacy tooling | No `wp_register_personal_data_exporter` / `wp_register_personal_data_eraser` for chat/support data; no privacy policy suggested text hook for third-party (OpenAI). |
| **Medium** | Plugin root README | Repository may have removed root `README.md`; ensure user-facing install docs exist for distribution. |

---

## 5. Recommended next actions

1. Add `current_user_can( 'manage_woocommerce' )` (or stricter) to `handle_chat_request()`, or only register the shortcode for logged-in administrators.
2. Extend `uninstall.php`: `DROP` support requests and email digests tables; `wp_clear_scheduled_hook` for all plugin crons (including digest interval).
3. Move `load_plugin_textdomain` to an `init` callback (keep loader init minimal on `plugins_loaded`).
4. Add minimal personal data exporter/eraser for chat history rows tied to a user ID.
5. Finalize plugin header + add `readme.txt` if targeting WordPress.org.

---

## 6. Disclaimer

This is a **manual/static** review, not a substitute for penetration testing, WooCommerce.com’s current submission guide, or WordPress.org Plugin Review Team feedback. Criteria change; verify against official documentation before submission.
