# WooCommerce Plugin Research & Architecture Summary

This document consolidates the key responses from our recent discussions about building and launching an AI-powered WooCommerce plugin.

---

## 1. Architecture: WooCommerce → Backend → LLM

### High-Level Flow

```
WooCommerce Plugin → Your Backend API → aiProvider.js → Groq/OpenAI LLM → Response back to Plugin
```

### Detailed Sequence

1. **Plugin gathers data** using WooCommerce PHP helpers (e.g., `wc_get_orders()`).
2. **Plugin sends data + user question** to a backend endpoint such as `POST /api/woocommerce/ask`.
3. **Backend processes data** (optional aggregation/sampling) and invokes `quickAnalysis()` from `aiProvider.js`.
4. **aiProvider.js** selects the LLM provider:
   ```javascript
   const model = new ChatGroq({
     apiKey: process.env.GROQ_API_KEY,
     model: "llama-3.3-70b-versatile",
     temperature: 0.7,
   });
   const response = await model.invoke(prompt);
   ```
5. **Groq/OpenAI API** returns JSON containing answer, insights, and optional chart data.
6. **Backend returns the LLM response** to the plugin.
7. **Plugin renders** the insights inside WordPress/WooCommerce (admin dashboard or shortcode).

### Handling Large Datasets

| Approach | Max Rows | Payload Size | Speed | Complexity | Recommended Use |
|----------|----------|--------------|-------|------------|-----------------|
| Direct send | ~1,000 | ~500KB | Fast | Low | Small stores |
| Smart sampling & aggregation | ~100,000 | ~50KB | Fast | Medium | Medium stores |
| Backend fetch via REST API | Unlimited | ~1KB | Medium | High | Large stores |
| Streaming / chunked uploads | Unlimited | Chunked | Slow | Very high | Enterprise |

**Recommended Strategy:** implement smart sampling first, then support backend-driven fetches for larger datasets. This keeps plugin payloads small while allowing your backend to retrieve exactly what it needs via WooCommerce REST endpoints.

---

## 2. WordPress/WooCommerce Plugin Structure

```
dataviz-ai-woocommerce-plugin/
├── dataviz-ai-woocommerce.php        # main plugin file
├── includes/
│   ├── class-dataviz-ai-loader.php
│   ├── class-dataviz-ai-admin.php
│   ├── class-dataviz-ai-api-client.php
│   ├── class-dataviz-ai-data-fetcher.php
│   ├── class-dataviz-ai-chat-widget.php
│   └── class-dataviz-ai-ajax-handler.php
├── admin/
│   ├── css/, js/, views/
├── public/
│   ├── css/, js/, views/
├── languages/
│   └── dataviz-ai-woocommerce.pot
└── uninstall.php
```

### Key Classes

- **`Dataviz_AI_Admin`** adds dashboard pages, registers settings (API URL, key), and loads views.
- **`Dataviz_AI_API_Client`** handles calls to your backend (`/api/woocommerce/ask`, `/api/chat`).
- **`Dataviz_AI_Data_Fetcher`** pulls WooCommerce data via PHP helpers (`wc_get_orders()`, `wc_get_products()`, etc.) and can perform on-site aggregation/sampling.
- **`Dataviz_AI_Chat_Widget`** renders the AI chat interface (shortcode or floating widget) and queues public assets.
- **`Dataviz_AI_AJAX_Handler`** exposes AJAX endpoints for analyzing data or sending chat messages.

### Where the LLM Call Occurs

```javascript
// aiProvider.js
async function quickAnalysis(userMessage, csvContext, provider = 'groq') {
  const model = getAIModel(provider); // ChatGroq or ChatOpenAI
  const prompt = `You are an expert data analyst...`;
  const response = await model.invoke(prompt); // <— actual LLM call
  return JSON.parse(response.content.match(/\{[\s\S]*\}/)[0]);
}
```

Environment variables (`GROQ_API_KEY`, `OPENAI_API_KEY`, `AI_PROVIDER`) determine which LLM is invoked.

---

## 3. Competitive Landscape & Pricing (Nov 2025)

| Product | Entry Cost | Higher Tiers / Notes |
| --- | --- | --- |
| **Jetpack CRM** (Automattic) | Free core plugin | Bundles: Freelancer $11/mo, Entrepreneur $17/mo, Reseller $30/mo. Individual add-ons $29–$79. |
| **Metorik** | Usage-based | 0 orders – $50/mo; 500–1k – $100/mo; 1k–2.5k – $150/mo; 2.5k–5k – $200/mo; 5k–10k – $250/mo; 10k–20k – $300/mo. |
| **AI Engine (WP-ChatGPT)** | Free core | Pro: Single site $89/yr, 5 sites $149/yr, Unlimited $299/yr. |
| **Tidio** | Free plan (50 AI chats/mo) | Paid: Starter $29/mo, Growth $59/mo, Tidio+ from $394/mo (custom). |
| **Intercom** | $39 per seat/mo | Advanced $99/seat/mo, enterprise custom pricing plus add-on modules. |

Usage (approximate active customers):

- **Jetpack CRM**: 40,000+ installations (WordPress.org).
- **Metorik**: ~9,000 WooCommerce stores (public statements).
- **AI Engine**: 50,000+ installations (WordPress.org).
- **Tidio**: 300,000+ businesses across platforms.
- **Intercom**: 25,000+ paying customers globally.

Support ownership:

- Jetpack CRM → Automattic
- Metorik → Metorik Pty Ltd
- AI Engine → Meow Apps
- Tidio → Tidio LLC
- Intercom → Intercom, Inc.

All are active companies that maintain and support their offerings.

---

## 4. Funding & Company Formation Options

- **Bootstrapping:** minimal infrastructure costs (domain, hosting, plugin dev). Charge early users.
- **Customer-funded:** pre-sell charter plans, lifetime deals, or agency partnerships to finance development.
- **Grants & credit programs:** WordPress/Automattic initiatives, regional innovation grants, cloud credits (AWS/GCP).
- **Micro-investors / angels:** WordPress ecosystem investors, SaaS-focused syndicates, friends & family.
- **Revenue-based financing:** once recurring revenue grows, explore Stripe Capital, Pipe, Capchase, etc.

Recommendation: build an MVP, validate with paying pilot stores, then formalize a company once data handling and revenue justify it.

---

## 5. Simple WooCommerce Plugin Walkthrough

1. **Set up WordPress & WooCommerce locally** (Local, DevKinsta, MAMP, or Docker).
2. **Import sample data** (`dummy-data.xml` from WooCommerce repository).
3. **Create plugin folder** `wp-content/plugins/sample-woocommerce-plugin/`.
4. **Add bootstrap PHP file** (see `SIMPLE_WOOCOMMERCE_PLUGIN_GUIDE.md` for full code).
5. **Activate plugin** and verify sample data displays in the admin menu.
6. **Iterate**: add product/customer queries, filtering, charts, REST endpoints.
7. **Connect to backend** to send sample data and render AI-powered insights.

---

## 6. Glossary (WordPress & WooCommerce)

- **WordPress:** open-source CMS used by 40%+ of websites. Supports themes and plugins.
- **WooCommerce:** Automattic’s eCommerce plugin for WordPress. Adds products, orders, checkout, payments, shipping, and an extensive extension ecosystem.

---

## 7. Next Steps Checklist

- [ ] Finalize MVP architecture (sampling + backend fetch).
- [ ] Scaffold plugin classes and REST endpoints.
- [ ] Build backend `/api/woocommerce/ask` endpoint leveraging `aiProvider.js`.
- [ ] Implement privacy/consent UI for data transfers.
- [ ] Produce marketing site and pre-sale offer.
- [ ] Decide on business structure when ready to charge customers.

---

This summary should serve as a central reference while you prototype, pitch, or plan funding for the WooCommerce AI plugin. Ping me if you want additional sections (e.g., legal templates, pricing calculators, or UI wireframes).
