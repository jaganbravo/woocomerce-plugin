# API key management (Dataviz AI for WooCommerce)

This document explains how **OpenAI‑compatible** credentials are loaded, and how a store owner (or developer) can supply them. It is written for people who are new to self‑hosted WordPress.

---

## 1. What is WordPress (in this context)?

**WordPress** is open‑source **PHP software** — a **content management system (CMS)** — that you **install** on a **web server**. It uses a **database** (MySQL or MariaDB) to store content, users, and plugin settings.

- **WordPress.org** = the self‑hosted product: *you* choose hosting, upload the files, and the site runs on **your** (or your client’s) infrastructure.
- **WordPress.com** = a *hosted* service (Automattic runs the stack for you on **their** side). It is a different product model, not the same as “I bought hosting and installed WordPress.”

**WooCommerce** is a **plugin** that runs *inside* WordPress. Your products and orders are stored in the **database** for **that** installation — not on a single global “WooCommerce server” for all merchants. Each shop is a separate WordPress site on **some** host.

---

## 2. What is a “managed host” / “managed WordPress”?

A **web host** is the company (or your own server) that provides:

- a machine or container,
- a **web server** (e.g. nginx, Apache),
- **PHP** and often **MySQL**,
- sometimes backups, SSL, staging, and a **control panel**.

**“Managed WordPress”** (or a **managed** host) usually means: the provider **takes care of the server and WordPress stack** (updates, caching, security, support) for **that** account. The site still **belongs to** the store owner; the host **operates the infrastructure** — it is *not* the same as “WooCommerce hosts every store centrally.”

**Why it matters for API keys:** some managed hosts let you set **environment variables** in a dashboard, which is one way to pass `OPENAI_API_KEY` to PHP (see below).

---

## 3. Where the plugin reads the key (priority order)

The plugin uses `Dataviz_AI_API_Client::get_api_key()`. The **first** non‑empty value wins:

| Priority | Source | Details |
|----------|--------|---------|
| **1** | **Environment** | `OPENAI_API_KEY`, or if empty `DATAVIZ_AI_API_KEY` |
| **2** | **PHP constant** | `define( 'DATAVIZ_AI_API_KEY', 'sk-...' );` — must be defined *before* the plugin needs it (e.g. in `wp-config.php` or `config.php` loaded by the plugin) |
| **3** | **WordPress options** | Option key `dataviz_ai_wc_settings`, array key `api_key` — for backward compatibility; a **Settings** page in the admin can save here if you add one |

**If** priority 1 or 2 is set, it **overrides** the value stored in the database. That is intentional: production sites often use env/constant and keep secrets out of the options table.

### Custom API base URL (optional)

For a **non‑default** API endpoint (e.g. custom proxy), the plugin uses the same kind of order for **URL**:

1. `DATAVIZ_AI_API_BASE_URL` (environment)
2. `define( 'DATAVIZ_AI_API_BASE_URL', 'https://...' );`
3. `dataviz_ai_wc_settings['api_url']` in the database

If no custom base URL is set, direct OpenAI chat uses the default OpenAI URL in code.

---

## 4. Options to supply the key (practical)

### A. Environment variables (server / host)

**Best for:** Docker, PaaS, and many **managed** hosts with an “Environment variables” (or similar) screen.

- Set `OPENAI_API_KEY` = your secret key, **or** `DATAVIZ_AI_API_KEY` if you prefer a namespaced name.
- Optional: `DATAVIZ_AI_API_BASE_URL` for a custom base URL.
- **How** you set them depends on the host (panel, `docker-compose`, Kubernetes secrets, etc.) — not on WordPress itself.

### B. `wp-config.php` (root of the WordPress install)

**Best for:** shared hosting, single‑site installs, when you can edit the root `wp-config.php` (or the host offers a “snippet” to add `define` lines).

Add **above** the line that says to stop editing (or follow your host’s doc):

```php
define( 'DATAVIZ_AI_API_KEY', 'sk-...' );
// Optional:
// define( 'DATAVIZ_AI_API_BASE_URL', 'https://your-proxy.example.com' );
```

**Note:** uses the **constant** `DATAVIZ_AI_API_KEY` (not the env var `OPENAI_API_KEY` in PHP — that one is only read from the environment in this plugin’s client).

### C. `config.php` in the plugin directory (copy from `config.php.example`)

**Best for:** deployments where you want secrets **next to the plugin** without touching core WordPress files.

1. In the **plugin** folder (same place as the main `dataviz-ai-woocommerce.php`), copy `config.php.example` → `config.php`.
2. Fill in:
   - `define( 'DATAVIZ_AI_API_KEY', 'sk-...' );`
   - optionally `DATAVIZ_AI_API_BASE_URL`
3. **Do not commit** `config.php` to public Git — the repo’s `.gitignore` usually excludes it. Ship only `config.php.example` in the distributed ZIP.

The main plugin file **loads** `config.php` automatically if the file exists.

### D. WordPress options (database) — `dataviz_ai_wc_settings['api_key']`

**Best for:** a future **Settings** page in **wp-admin** where the user pastes the key and you call `update_option( 'dataviz_ai_wc_settings', $merged )`, merging with any existing `api_url` / other keys so you do not overwrite unrelated data.

- Easiest for **non‑technical** merchants.
- **Lower priority** than env/constant: if env or `DATAVIZ_AI_API_KEY` is set, the stored option is **not** used for outbound calls.
- **Hardening (optional):** you can store the value **encrypted in the options table** (e.g. with PHP OpenSSL) and decrypt only at runtime. Derive a site-specific secret from **`wp-config.php`** (`AUTH_KEY` + salts, or a dedicated `define( 'DATAVIZ_AI_ENCRYPTION_KEY', '...' )`) so a raw database dump is not a plaintext key. Rotating those salts/keys may require the user to **re‑enter** the API key if you do not add a migration.

### E. What does *not* apply here

- **“Setting the key in OpenAI’s control panel”** only creates the key on OpenAI’s side. You must still **copy** it to **one** of the methods above (or into your plugin’s settings once implemented).
- **WooCommerce** does not store your OpenAI key; this plugin (or the environment) does.

---

## 5. Getting a key from OpenAI

1. Create or sign in to an account at the provider (e.g. OpenAI).
2. In the **API keys** section, create a **secret** key.
3. **Paste** that secret into **one** of the methods in section 4 (or into your product’s **Settings** UI if you add it).
4. Keep billing/limits in mind on the provider’s side; “invalid” or 429 errors are often key or quota related.

---

## 6. Security reminders

- Treat API keys like **passwords**; do not share them in support tickets or screenshots.
- Prefer **env** or **define** in production to avoid long‑lived keys in the database, when possible.
- If you store the key in **options**, **encryption at rest** (see §4D) tightens real-world exposure to DB backups and SQL exports; it does not replace locking down **admin** access and a healthy hosting posture.
- Restrict who can see any **Settings** field — see **§7** and pick a capability that matches your store’s trust model.
- Use HTTPS for the **site** and keep WordPress, WooCommerce, and plugins updated.

---

## 7. Who can access the plugin (WordPress roles / capabilities)

Access is not “any logged-in user.” The plugin code checks **WordPress capabilities**; **who** has them depends on their **role** (WooCommerce **Shop manager**, **Administrator**, etc. — *not* customers or subscribers in default setups).

| Area | Typical capability in code | Who usually has it |
|------|----------------------------|--------------------|
| **Dataviz AI** admin menu (chat, FAQ, Onboarding, submenus for digests / support) | `manage_woocommerce` | **Shop manager** and **Administrator** (on WooCommerce sites). |
| **Chat** shortcode, **analyze / chat** AJAX, support requests in admin (where gated) | `manage_woocommerce` | Same. |
| **Onboarding reset** (AJAX) | `manage_options` | Usually **Administrator** only — **not** the Shop manager role in a default install. |

**Implications for API keys**

- A **Settings** page that saves the key to the database should use **`current_user_can( ... )` before** saving or showing the key. A common choice is **`manage_woocommerce`** so the same people who can use the chat (shop managers) can **configure the key**; if you only want full admins to see secrets, use **`manage_options`** instead. Document whichever you implement in the readme.

**Implications for env / `config.php`**

- The **key** in env or a **file** is enforced by the **server**; WordPress does not “hide” it from the database user. **File permissions** and **host access** still matter. Only trusted roles should be able to **read** or **export** the site’s configuration (backups, plugins that dump options, etc.).

---

## 8. See also

- `config.php.example` in the plugin root
- `readme.txt` — Installation and FAQ
- `includes/class-dataviz-ai-api-client.php` — `get_api_key()` and `get_api_url()`

---

*Last updated: aligned with the plugin’s API client resolution order and current capability checks. If you add an admin “Settings” screen, document the chosen capability, UI location, and storage here and in `readme.txt`.*
