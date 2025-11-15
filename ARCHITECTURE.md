# Dataviz AI WooCommerce Plugin - Architecture Overview

## 📐 System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    DOCKER INFRASTRUCTURE                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐    │
│  │   wp_db      │    │   wp_app     │    │   wp_pma     │    │
│  │  (MariaDB)   │◄───│  (WordPress) │    │ (phpMyAdmin) │    │
│  │  Port: 3306  │    │  Port: 8080  │    │  Port: 8081  │    │
│  └──────────────┘    └──────────────┘    └──────────────┘    │
│         ▲                   │                                    │
│         │                   │                                    │
│         └───────────────────┘                                    │
│              (Database Connection)                               │
│                                                                  │
│  ┌──────────────┐                                               │
│  │   wp_cli     │                                               │
│  │ (WP-CLI Tool)│                                               │
│  └──────────────┘                                               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    WORDPRESS PLUGIN LAYER                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  dataviz-ai-woocommerce.php (Main Entry Point)          │  │
│  │  - Defines constants                                      │  │
│  │  - Registers activation/deactivation hooks              │  │
│  │  - Initializes plugin on 'plugins_loaded'                │  │
│  └──────────────────┬───────────────────────────────────────┘  │
│                      │                                            │
│                      ▼                                            │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  class-dataviz-ai-loader.php (Orchestrator)             │  │
│  │  - Loads all dependencies                                 │  │
│  │  - Instantiates components                                 │  │
│  │  - Registers WordPress hooks                               │  │
│  └──────┬──────────────┬──────────────┬──────────────────────┘  │
│         │              │              │                          │
│         ▼              ▼              ▼                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────────┐                  │
│  │  Admin   │  │  AJAX    │  │  Chat Widget │                  │
│  │  Class   │  │  Handler │  │  Class       │                  │
│  └────┬─────┘  └────┬─────┘  └──────┬───────┘                  │
│       │             │                │                          │
│       ▼             ▼                ▼                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────────┐                  │
│  │ Data     │  │  API     │  │  API Client │                  │
│  │ Fetcher  │  │  Client  │  │  (shared)   │                  │
│  └──────────┘  └──────────┘  └──────────────┘                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    EXTERNAL AI SERVICES                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────┐    ┌──────────────────────┐         │
│  │  Custom Backend API  │    │   OpenAI API          │         │
│  │  (Optional)          │    │   (Direct)            │         │
│  │  - Custom endpoints  │    │   - gpt-4o-mini        │         │
│  │  - Custom logic      │    │   - Chat completions  │         │
│  └──────────────────────┘    └──────────────────────┘         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## 🏗️ Project Structure

```
woocomerce-plugin/
│
├── dataviz-ai-woocommerce-plugin/     # Main Plugin Directory
│   ├── dataviz-ai-woocommerce.php     # Plugin entry point
│   │
│   ├── includes/                      # Core PHP Classes
│   │   ├── class-dataviz-ai-loader.php
│   │   │   └── Orchestrates all components, registers hooks
│   │   │
│   │   ├── class-dataviz-ai-admin.php
│   │   │   └── Admin dashboard, settings page, UI rendering
│   │   │
│   │   ├── class-dataviz-ai-ajax-handler.php
│   │   │   └── Handles AJAX requests (analysis & chat)
│   │   │
│   │   ├── class-dataviz-ai-api-client.php
│   │   │   └── API communication (custom backend or OpenAI)
│   │   │
│   │   ├── class-dataviz-ai-data-fetcher.php
│   │   │   └── Fetches WooCommerce data (orders, products, customers)
│   │   │
│   │   └── class-dataviz-ai-chat-widget.php
│   │       └── Frontend chat widget (shortcode)
│   │
│   ├── admin/                         # Admin Assets
│   │   ├── css/
│   │   │   └── admin.css              # Admin dashboard styles
│   │   └── js/
│   │       └── admin.js               # Admin JavaScript (AJAX forms)
│   │
│   ├── public/                        # Frontend Assets
│   │   ├── css/
│   │   │   └── chat-widget.css        # Chat widget styles
│   │   └── js/
│   │       └── chat-widget.js         # Chat widget JavaScript
│   │
│   └── languages/                     # Translation files
│
├── docker/                            # Docker Environment
│   ├── docker-compose.yml             # Container orchestration
│   ├── README.md                      # Docker setup instructions
│   └── wordpress/                     # WordPress installation
│       └── wp-content/
│           └── plugins/
│               └── dataviz-ai-woocommerce-plugin/  # Plugin (copied here)
│
└── Documentation/
    ├── ARCHITECTURE.md                # This file
    ├── PLUGIN_GUIDE.md                # Plugin development guide
    ├── QUICK_START.md                 # Quick start checklist
    └── README.md                       # Project overview
```

## 🔄 Data Flow

### 1. Admin Dashboard Request Flow

```
User (Browser)
    │
    ▼
WordPress Admin Page (wp-admin)
    │
    ▼
Dataviz_AI_Admin::render_admin_page()
    │
    ├──→ Renders AI Chat Form
    │
    └──→ Enqueues admin.js
            │
            ▼
        User submits question
            │
            ▼
        AJAX POST to admin-ajax.php
            │
            ▼
        Dataviz_AI_AJAX_Handler::handle_analysis_request()
            │
            ├──→ Dataviz_AI_Data_Fetcher
            │       └──→ Fetches WooCommerce data
            │
            └──→ Dataviz_AI_API_Client
                    │
                    ├──→ has_custom_backend()?
                    │       │
                    │       ├── YES → POST to custom backend
                    │       │           └──→ api/woocommerce/ask
                    │       │
                    │       └── NO → send_openai_chat()
                    │                   └──→ OpenAI API directly
                    │
                    ▼
            Response formatted & returned
            │
            ▼
        admin.js displays result
```

### 2. Frontend Chat Widget Flow

```
Frontend Page (with [dataviz_ai_chat] shortcode)
    │
    ▼
Dataviz_AI_Chat_Widget::render_shortcode()
    │
    ├──→ Enqueues chat-widget.js & CSS
    │
    └──→ Renders chat form HTML
            │
            ▼
        User sends message
            │
            ▼
        AJAX POST to admin-ajax.php
            │
            ▼
        Dataviz_AI_AJAX_Handler::handle_chat_request()
            │
            ├──→ Dataviz_AI_Data_Fetcher
            │       └──→ Gets recent orders (context)
            │
            └──→ Dataviz_AI_API_Client
                    │
                    ├──→ Custom backend OR OpenAI
                    │
                    ▼
            Response returned
            │
            ▼
        chat-widget.js displays message
```

## 🧩 Component Responsibilities

### Core Classes

| Class | Responsibility |
|-------|---------------|
| **Dataviz_AI_Loader** | Orchestrates plugin initialization, loads dependencies, registers hooks |
| **Dataviz_AI_Admin** | Admin dashboard UI, settings page, asset enqueuing |
| **Dataviz_AI_AJAX_Handler** | Handles AJAX requests (analysis & chat), formats responses |
| **Dataviz_AI_API_Client** | Manages API communication (custom backend or OpenAI) |
| **Dataviz_AI_Data_Fetcher** | Retrieves WooCommerce data (orders, products, customers) |
| **Dataviz_AI_Chat_Widget** | Frontend chat widget shortcode and public assets |

### Key Features

1. **Dual API Mode**
   - Custom Backend: If API URL is configured, uses custom endpoints
   - Direct OpenAI: If only API key provided, calls OpenAI directly

2. **WooCommerce Integration**
   - Fetches orders, products, and customer data
   - Formats data for AI context
   - Uses WooCommerce hooks and functions

3. **Security**
   - Nonce verification for AJAX requests
   - Capability checks (`manage_woocommerce`)
   - Input sanitization and output escaping

## 🐳 Docker Infrastructure

### Services

| Service | Image | Purpose | Ports |
|---------|-------|---------|-------|
| **wp_db** | mariadb:11.3 | WordPress database | 3306 (internal) |
| **wp_app** | wordpress:6.6-php8.2-apache | WordPress application | 8080:80 |
| **wp_cli** | wordpress:cli-php8.2 | WP-CLI tool for management | N/A |
| **wp_pma** | phpmyadmin/phpmyadmin:5 | Database administration | 8081:80 |

### Volumes

- `db_data`: Persistent MariaDB data
- `./wordpress`: WordPress files (mounted from host)

## 🔌 Integration Points

### WordPress Hooks

```php
// Plugin Initialization
plugins_loaded (priority: 20)
    └──→ dataviz_ai_wc_init()

// Admin Hooks
admin_menu
    └──→ register_menu_page()
admin_init
    └──→ register_settings()
admin_enqueue_scripts
    └──→ enqueue_assets()

// AJAX Hooks
wp_ajax_dataviz_ai_analyze
wp_ajax_nopriv_dataviz_ai_analyze
    └──→ handle_analysis_request()

wp_ajax_dataviz_ai_chat
wp_ajax_nopriv_dataviz_ai_chat
    └──→ handle_chat_request()

// Public Hooks
wp_enqueue_scripts
    └──→ register_assets() (chat widget)
```

### WooCommerce Integration

- Uses `wc_get_orders()` for order data
- Uses `wc_get_products()` for product data
- Uses `wc_get_customer_total_spent()` for customer metrics
- Requires WooCommerce to be active (dependency check)

## 📊 API Communication

### Custom Backend Mode

```http
POST {api_url}/api/woocommerce/ask
Headers:
  Authorization: Bearer {api_key}
  Content-Type: application/json
Body:
  {
    "question": "What are the key trends?",
    "orders": [...],
    "products": [...],
    "customers": {...}
  }
```

### OpenAI Direct Mode

```http
POST https://api.openai.com/v1/chat/completions
Headers:
  Authorization: Bearer {api_key}
  Content-Type: application/json
Body:
  {
    "model": "gpt-4o-mini",
    "messages": [
      {"role": "system", "content": "..."},
      {"role": "user", "content": "..."}
    ]
  }
```

## 🔐 Security Architecture

1. **Authentication**
   - WordPress nonces for AJAX requests
   - Capability checks (`manage_woocommerce`)

2. **Input Validation**
   - All inputs sanitized (`sanitize_text_field`, `esc_url_raw`)
   - Output escaped (`esc_html`, `wp_kses_post`)

3. **API Security**
   - API keys stored in WordPress options (encrypted at rest)
   - Bearer token authentication for API calls

## 🚀 Deployment Architecture

```
Development (Local)
    │
    ├──→ Docker Compose (localhost:8080)
    │       └──→ Plugin in wp-content/plugins/
    │
Production (WordPress Site)
    │
    ├──→ WordPress Installation
    │       └──→ Plugin uploaded via admin or FTP
    │
    └──→ API Configuration
            ├──→ Custom Backend URL (optional)
            └──→ API Key (required)
```

## 🔄 Update Flow

1. **Code Changes** → Git commit
2. **Git Push** → GitHub repository
3. **Git Pull** → Local development
4. **Copy to Docker** → `cp -R plugin docker/wordpress/wp-content/plugins/`
5. **Activate** → WordPress admin or WP-CLI

---

**Last Updated:** November 15, 2025  
**Version:** 0.1.0

