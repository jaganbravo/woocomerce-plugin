# Frequently Asked Questions (FAQ)
## Dataviz AI for WooCommerce

---

## Table of Contents

1. [General Questions](#general-questions)
2. [Installation & Setup](#installation--setup)
3. [Configuration](#configuration)
4. [Usage](#usage)
5. [Features](#features)
6. [Troubleshooting](#troubleshooting)
7. [Technical Questions](#technical-questions)
8. [Support](#support)

---

## General Questions

### What is Dataviz AI for WooCommerce?

**Answer:** Dataviz AI for WooCommerce is an AI-powered analytics plugin that allows WooCommerce store owners to ask questions about their store data and receive intelligent, data-driven answers. It provides a ChatGPT-style interface in your WordPress admin dashboard where you can query your orders, products, customers, and other store metrics using natural language.

### What are the main features?

**Answer:** The plugin includes:
- **AI Chat Interface**: Natural language queries about your store data
- **Admin Dashboard**: ChatGPT-style interface in WordPress admin
- **Data Analysis**: Access to orders, products, customers, categories, tags, coupons, refunds, and stock data
- **Streaming Responses**: Real-time chat experience with streaming responses
- **Chat History**: Save and review previous conversations
- **Function Calling**: Intelligent tool selection based on your questions
- **Large Dataset Handling**: Efficient processing of large amounts of data through statistics and sampling

### Do I need coding knowledge to use this plugin?

**Answer:** No, the plugin is designed to be user-friendly. You simply type questions in natural language, and the AI will analyze your WooCommerce data and provide answers. Basic WordPress knowledge is helpful for installation and configuration.

### Is this plugin free?

**Answer:** Please check the plugin's pricing information on the official website or WordPress plugin directory. Pricing may vary based on your usage plan.

---

## Installation & Setup

### What are the system requirements?

**Answer:** The plugin requires:
- **WordPress**: Version 6.0 or higher
- **PHP**: Version 7.4 or higher
- **WooCommerce**: Version 6.0 or higher (must be installed and activated)
- **WooCommerce Tested**: Up to version 8.5

### How do I install the plugin?

**Answer:** 
1. Upload the `dataviz-ai-woocommerce-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated before activation
4. Navigate to the **Dataviz AI** menu in your WordPress admin sidebar

### Can I install this if WooCommerce is not active?

**Answer:** No, the plugin requires WooCommerce to be installed and activated. If you try to activate the plugin without WooCommerce, it will automatically deactivate and display an error message.

### What happens during plugin activation?

**Answer:** During activation, the plugin:
- Verifies WooCommerce is installed and active
- Creates database tables for chat history and feature requests
- Schedules daily cleanup tasks for old chat messages
- Sets up necessary WordPress hooks and filters

---

## Configuration

### How do I configure the API connection?

**Answer:**
1. Go to **Dataviz AI** in your WordPress admin sidebar
2. Navigate to the **Settings** page
3. Enter your backend API base URL
4. Configure your API key (preferably via environment variables for security)
5. Save the settings

### How do I set up the API key using environment variables?

**Answer:** For security, it's recommended to set your API key as an environment variable:
- Set `OPENAI_API_KEY` or `DATAVIZ_AI_API_KEY` in your server's environment variables
- The plugin will automatically read from these variables
- This prevents the API key from being stored in the WordPress database

### What API endpoints does the plugin use?

**Answer:** The plugin communicates with these backend endpoints:
- `/api/woocommerce/ask` - For WooCommerce data queries
- `/api/chat` - For general chat interactions

You may need to adjust these endpoints to match your specific API routes.

### Can I use this without a backend API?

**Answer:** No, the plugin requires a backend API to process AI requests. The plugin sends WooCommerce data to your backend, which then communicates with the AI provider (such as OpenAI or Groq) and returns the results.

---

## Usage

### How do I start using the AI chat?

**Answer:**
1. Navigate to **Dataviz AI** in your WordPress admin
2. You'll see the chat interface
3. Type your question in natural language (e.g., "What are my top-selling products this month?")
4. Press Enter or click Send
5. The AI will analyze your data and provide an answer

### What kind of questions can I ask?

**Answer:** You can ask questions about:
- **Orders**: "Show me orders from last week", "What's the total revenue this month?"
- **Products**: "What are my best-selling products?", "Which products have low stock?"
- **Customers**: "How many new customers did I get this month?", "Who are my top customers?"
- **Categories & Tags**: "What categories are most popular?"
- **Coupons**: "Which coupons were used most?"
- **Refunds**: "How many refunds were processed this month?"
- **Stock**: "Which products are out of stock?"

### How do I use the chat widget on the frontend?

**Answer:** 
1. Use the `[dataviz_ai_chat]` shortcode on any page or post
2. The shortcode will display a lightweight AI chat widget
3. Visitors can interact with the chat widget to ask questions about your store

Example: `[dataviz_ai_chat]`

### Can I view my chat history?

**Answer:** Yes, the plugin saves your chat history. You can view previous conversations in the admin dashboard. The plugin also includes a daily cleanup task that removes old messages to keep the database optimized.

### How does the quick analysis form work?

**Answer:** The quick analysis form allows you to quickly send order data to the backend for analysis. It normalizes WooCommerce data before sending it to your backend API endpoint.

---

## Features

### What data can the AI access?

**Answer:** The plugin can access:
- Orders (with filtering, statistics, sampling, and time-series aggregation)
- Products (list and statistics)
- Customers (list and statistics)
- Categories and Tags
- Coupons
- Refunds
- Stock levels

### How does the function calling work?

**Answer:** The plugin uses intelligent function calling where the AI (LLM) automatically decides which data tools to use based on your question. For example, if you ask about orders, it will use the orders tool; if you ask about products, it will use the products tool.

### Does the plugin handle large datasets?

**Answer:** Yes, the plugin includes smart handling for large datasets:
- **Statistics**: Provides aggregated statistics instead of raw data
- **Sampling**: Uses intelligent sampling for large datasets
- **Time-series Aggregation**: Groups data by time periods for efficient analysis
- **Backend Fetching**: Can fetch data via REST API for very large stores

### What is streaming responses?

**Answer:** Streaming responses (SSE - Server-Sent Events) provide a real-time chat experience where you see the AI's response appear word-by-word as it's generated, similar to ChatGPT. This creates a more interactive and engaging user experience.

---

## Troubleshooting

### The plugin won't activate. What should I do?

**Answer:** 
1. Ensure WooCommerce is installed and activated
2. Check that your WordPress version is 6.0 or higher
3. Verify PHP version is 7.4 or higher
4. Check for plugin conflicts by deactivating other plugins temporarily
5. Review the error message displayed during activation

### I'm getting API connection errors. How do I fix this?

**Answer:**
1. Verify your API base URL is correct in the settings
2. Check that your API key is properly configured (via environment variables or settings)
3. Ensure your backend API is running and accessible
4. Check server logs for detailed error messages
5. Verify firewall settings aren't blocking API requests
6. Test the API endpoint directly using a tool like Postman or curl

### The chat widget isn't appearing on my frontend. Why?

**Answer:**
1. Verify you've added the `[dataviz_ai_chat]` shortcode correctly
2. Check that the plugin's public CSS and JavaScript files are loading
3. Clear your browser cache and WordPress cache
4. Check browser console for JavaScript errors
5. Ensure your theme supports shortcodes

### Responses are slow or timing out. What can I do?

**Answer:**
1. Check your server's PHP execution time limits
2. Verify your backend API response times
3. For large datasets, the plugin uses sampling - this is normal
4. Check your server resources (CPU, memory)
5. Consider optimizing your WooCommerce database
6. Review API rate limits if using external AI services

### I'm seeing "WooCommerce required" error even though it's installed.

**Answer:**
1. Deactivate and reactivate WooCommerce
2. Deactivate and reactivate the Dataviz AI plugin
3. Check that WooCommerce is the correct version (6.0+)
4. Clear WordPress cache
5. Check for conflicting plugins

### Chat history is not saving. What should I check?

**Answer:**
1. Verify the database tables were created during activation
2. Check database permissions
3. Review WordPress debug logs for database errors
4. Try deactivating and reactivating the plugin to recreate tables
5. Check that the scheduled cleanup task isn't removing messages too aggressively

### The AI is giving incorrect answers about my data.

**Answer:**
1. Verify your WooCommerce data is accurate and up-to-date
2. Check that the data fetchers are working correctly
3. Review the data being sent to the API (check network requests in browser dev tools)
4. Ensure your backend API is processing data correctly
5. Try rephrasing your question for better clarity

---

## Technical Questions

### Can I customize the data fetchers?

**Answer:** Yes, you can extend the data fetcher methods in `class-dataviz-ai-data-fetcher.php`. The plugin provides helper methods that you can customize or extend based on your specific analytics needs.

### How do I add custom tools or data sources?

**Answer:** You can extend the plugin by:
1. Adding new data fetcher methods in `class-dataviz-ai-data-fetcher.php`
2. Registering new tools in your backend API
3. Updating the function calling schema to include new tools
4. Modifying the AJAX handlers to support new data types

### Can I change the AI model or provider?

**Answer:** The AI model and provider are configured on your backend API, not in the WordPress plugin. You can modify your backend to use different providers (OpenAI, Groq, etc.) or different models. The plugin simply sends requests to your backend API.

### How does the plugin handle data privacy?

**Answer:** 
- Data is sent to your configured backend API
- Chat history is stored in your WordPress database
- You control what data is sent and where it goes
- Review your backend API's privacy policy and data handling
- Consider implementing data retention policies

### Can I export chat history?

**Answer:** Currently, chat history is stored in the database. You can:
1. Access it directly from the database
2. Use WordPress export tools
3. Create a custom export feature by extending the plugin
4. Use database backup tools to export the chat history table

### How do I translate the plugin?

**Answer:**
1. Generate a `.pot` file into the `languages/` directory using `wp i18n make-pot`
2. Create translation files for your language
3. Load the translations in your WordPress installation
4. The plugin uses the text domain `dataviz-ai-woocommerce`

### What database tables does the plugin create?

**Answer:** The plugin creates two custom tables:
1. **Chat History Table**: Stores conversation history
2. **Feature Requests Table**: Stores user feature requests

Both tables are created during plugin activation and removed during uninstallation (if you choose to delete data).

---

## Support

### Where can I get help?

**Answer:**
- Check this FAQ document first
- Review the plugin documentation in the `documentation/` folder
- Check the README.md file for setup instructions
- Contact the plugin author through the official support channel
- Review WordPress and WooCommerce forums for related issues

### How do I report a bug?

**Answer:** 
1. Document the issue clearly
2. Note your WordPress, WooCommerce, and PHP versions
3. Check WordPress debug logs for error messages
4. List steps to reproduce the issue
5. Contact support with this information

### Can I request new features?

**Answer:** Yes! The plugin includes a feature requests system. You can submit feature requests through the admin interface, and they will be stored in the database for review.

### Is there developer documentation?

**Answer:** Yes, check the following documentation files:
- `ARCHITECTURE.md` - Plugin architecture overview
- `ARCHITECTURE_DOCUMENTATION.md` - Detailed technical documentation
- `DATABASE_STRUCTURE.md` - Database schema information
- `PLUGIN_GUIDE.md` - Development guide
- Code comments in the plugin files

---

## Additional Resources

- **Quick Start Guide**: See `QUICK_START.md`
- **Plugin Guide**: See `PLUGIN_GUIDE.md`
- **Architecture Documentation**: See `ARCHITECTURE_DOCUMENTATION.md`
- **Testing Strategy**: See `TESTING_STRATEGY.md`

---

**Last Updated:** [Current Date]
**Plugin Version:** 0.1.0

For the most up-to-date information, please refer to the official plugin documentation or contact support.
