# Dataviz AI WooCommerce Plugin - Full Architecture Documentation

## Overview

This is a **WordPress/WooCommerce plugin** that integrates AI capabilities for analyzing store data. The architecture supports **two modes of operation**:

1. **Direct OpenAI Mode** (Current Default) - Plugin calls OpenAI API directly
2. **Custom Backend Mode** (Optional) - Plugin calls your custom backend API

**There is NO separate backend application** - it's purely a WordPress plugin. However, you CAN optionally connect it to an external backend if you build one.

---

## Current Architecture: Direct OpenAI Mode

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER BROWSER                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  WordPress Admin Dashboard                                │  │
│  │  - ChatGPT-style chat interface                           │  │
│  │  - API Settings page                                      │  │
│  └──────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ AJAX Request (Fetch API with Streaming)
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│              WORDPRESS/WOOCOMMERCE PLUGIN                        │
│                   (All PHP, runs on WordPress)                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Dataviz_AI_AJAX_Handler                                 │  │
│  │  - Receives user question                                │  │
│  │  - Handles streaming responses                           │  │
│  └──────────────────────────────────────────────────────────┘  │
│                             │                                    │
│                             ▼                                    │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Dataviz_AI_Data_Fetcher                                 │  │
│  │  - get_recent_orders()                                   │  │
│  │  - get_top_products()                                    │  │
│  │  - get_customer_summary()                                │  │
│  │  - get_customers()                                       │  │
│  └──────────────────────────────────────────────────────────┘  │
│                             │                                    │
│                             ▼                                    │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Dataviz_AI_API_Client                                   │  │
│  │  - send_openai_chat()                                    │  │
│  │  - send_openai_chat_stream() (cURL streaming)            │  │
│  │  - Uses Function Calling to let LLM choose tools         │  │
│  └──────────────────────────────────────────────────────────┘  │
│                             │                                    │
└─────────────────────────────┼────────────────────────────────────┘
                              │
                              │ HTTP/HTTPS
                              │ (Streaming SSE)
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    OPENAI API                                    │
│  - gpt-4o-mini                                                   │
│  - Function Calling (Tools)                                      │
│  - Streaming Chat Completions                                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## Component Breakdown

### 1. **Frontend (Browser)**

#### Admin Dashboard (`admin/js/admin.js`, `admin/css/admin.css`)
- **ChatGPT-style chat interface**
- Streaming message display
- Auto-scrolling, loading animations
- Keyboard shortcuts (Enter to send, Shift+Enter for new line)

#### Files:
- `admin/js/admin.js` - Main JavaScript for chat interface
- `admin/css/admin.css` - Styling for ChatGPT-like UI
- `admin/views/` - Admin page templates (if any)

### 2. **WordPress Plugin Layer** (PHP)

#### Core Classes:

**`Dataviz_AI_Loader`** (`includes/class-dataviz-ai-loader.php`)
- Plugin initialization
- Registers WordPress hooks
- Wires all components together

**`Dataviz_AI_Admin`** (`includes/class-dataviz-ai-admin.php`)
- Admin dashboard UI rendering
- Settings page (API URL & Key)
- Asset enqueuing (CSS/JS)

**`Dataviz_AI_AJAX_Handler`** (`includes/class-dataviz-ai-ajax-handler.php`)
- Handles AJAX requests from frontend
- Routes to appropriate handlers:
  - `handle_analysis_request()` - Main chat handler
  - `handle_streaming_analysis()` - Streaming response handler
  - `handle_smart_analysis()` - OpenAI function calling logic
- Executes LLM-requested tools (get_recent_orders, etc.)

**`Dataviz_AI_API_Client`** (`includes/class-dataviz-ai-api-client.php`)
- **Direct OpenAI integration** (current default)
- `send_openai_chat()` - Regular chat completion
- `send_openai_chat_stream()` - Streaming chat (cURL)
- Optional: `post()` - For custom backend (if URL configured)

**`Dataviz_AI_Data_Fetcher`** (`includes/class-dataviz-ai-data-fetcher.php`)
- WooCommerce data access layer
- `get_recent_orders()` - Fetches orders with filters
- `get_top_products()` - Gets top-selling products
- `get_customer_summary()` - Customer metrics
- `get_customers()` - Customer list

**`Dataviz_AI_Chat_Widget`** (`includes/class-dataviz-ai-chat-widget.php`)
- Frontend shortcode `[dataviz_ai_chat]`
- Public-facing chat widget
- Enqueues public assets

### 3. **Data Layer** (WooCommerce)

- **Orders**: Retrieved via `wc_get_orders()`
- **Products**: Retrieved via `wc_get_products()`
- **Customers**: Retrieved via `WP_User_Query`
- **Customer Metrics**: Uses `wc_get_customer_total_spent()`

All data is stored in **WordPress/WooCommerce database** - no separate data layer.

### 4. **AI Service** (External)

#### OpenAI API (Current)
- **Endpoint**: `https://api.openai.com/v1/chat/completions`
- **Model**: `gpt-4o-mini` (default)
- **Features**:
  - Function Calling (Tools)
  - Streaming responses (Server-Sent Events)
  - Chat completions

#### Optional: Custom Backend API
- If you configure an API URL in settings
- Plugin sends data to your backend
- Your backend can then call any LLM provider
- **Not currently implemented** - just a placeholder

---

## Data Flow: Current Implementation

### User Asks a Question → Streaming Response

```
1. User types question in chat
   ↓
2. Frontend (admin.js)
   - Validates input
   - Sends Fetch API request with stream=true
   - Creates empty AI message container
   ↓
3. WordPress AJAX Handler (handle_analysis_request)
   - Checks nonce & permissions
   - Routes to handle_streaming_analysis()
   ↓
4. Smart Analysis Flow (handle_streaming_analysis)
   - Builds messages for LLM
   - Sends initial request with available tools
   ↓
5. LLM Decision (Function Calling)
   - LLM decides which tools to call
   - Returns tool_calls: ["get_recent_orders", etc.]
   ↓
6. Tool Execution (execute_tool)
   - Calls Dataviz_AI_Data_Fetcher methods
   - Fetches WooCommerce data
   - Returns data to LLM context
   ↓
7. Streaming Response (send_openai_chat_stream)
   - Uses cURL with WRITEFUNCTION callback
   - Reads OpenAI stream chunks
   - Sends chunks via Server-Sent Events
   ↓
8. Frontend (admin.js)
   - Reads stream via Fetch API
   - Accumulates chunks in message container
   - Updates UI in real-time
   ↓
9. Stream Complete
   - Displays final message
   - Enables input for next question
```

---

## Available LLM Tools (Function Calling)

The LLM can dynamically choose to call these tools based on user questions:

1. **`get_recent_orders`**
   - Parameters: `limit`, `status`, `date_from`, `date_to`
   - Returns: Array of order data

2. **`get_top_products`**
   - Parameters: `limit`
   - Returns: Top-selling products

3. **`get_customer_summary`**
   - Parameters: None
   - Returns: Total customers, avg lifetime value

4. **`get_customers`**
   - Parameters: `limit`
   - Returns: List of customers

---

## Configuration

### Settings Page
- **API URL** (Optional): Custom backend endpoint
- **API Key** (Required): OpenAI API key (stored in WordPress options)

### Current Mode
- **Direct OpenAI**: API URL is empty → calls OpenAI directly
- **Custom Backend**: API URL configured → sends to your backend (not implemented)

---

## File Structure

```
dataviz-ai-woocommerce-plugin/
│
├── dataviz-ai-woocommerce.php    # Plugin entry point
│
├── includes/                      # Core PHP classes
│   ├── class-dataviz-ai-loader.php
│   ├── class-dataviz-ai-admin.php
│   ├── class-dataviz-ai-ajax-handler.php
│   ├── class-dataviz-ai-api-client.php
│   ├── class-dataviz-ai-data-fetcher.php
│   └── class-dataviz-ai-chat-widget.php
│
├── admin/                         # Admin dashboard
│   ├── css/
│   │   └── admin.css             # ChatGPT-style styling
│   ├── js/
│   │   └── admin.js              # Chat interface + streaming
│   └── views/
│
├── public/                        # Frontend widget
│   ├── css/
│   ├── js/
│   └── views/
│
└── languages/                     # Translations
```

---

## Dependencies

### WordPress
- WordPress 6.0+
- PHP 7.4+

### WooCommerce
- WooCommerce 6.0+
- Required for plugin to function

### External APIs
- **OpenAI API** - For AI responses
- **cURL** - For streaming support (usually built into PHP)

---

## Security

1. **Authentication**
   - WordPress nonces for AJAX requests
   - Capability checks (`manage_woocommerce`)
   - Admin-only access

2. **Data Sanitization**
   - Input sanitization (`sanitize_text_field`, `esc_url_raw`)
   - Output escaping (`esc_html`, `wp_kses_post`)

3. **API Key Storage**
   - Stored in WordPress options table
   - Encrypted in transit (HTTPS)
   - No encryption at rest (WordPress standard)

---

## Future Enhancements (Optional Backend)

If you want to add a custom backend:

1. **Build Backend API**
   - Node.js/Express, Python/Flask, etc.
   - Endpoints: `/api/woocommerce/ask`, `/api/chat`
   - Handle LLM calls on backend

2. **Update Plugin**
   - Configure API URL in settings
   - Plugin sends data to your backend
   - Backend returns AI responses

3. **Benefits**
   - Centralized LLM management
   - Additional processing/middleware
   - Multi-store aggregation
   - Custom analytics

---

## Summary

**Current State**: 
- ✅ Pure WordPress plugin
- ✅ Direct OpenAI integration
- ✅ Streaming responses
- ✅ Function calling (LLM chooses tools)
- ✅ No separate backend needed

**Optional Future**:
- Custom backend API (if you want one)
- Multi-tenant support
- Advanced analytics
- Custom LLM providers

The plugin is **self-contained** and works entirely within WordPress with direct OpenAI API calls.

