## Dataviz AI WooCommerce – Data & Intent Architecture

### 1. High‑Level Flow

User question → UI (admin.js / chat-widget.js) → AJAX (`admin-ajax.php`) → `Dataviz_AI_AJAX_Handler` → `Dataviz_AI_Intent_Classifier` → PHP tool execution (`Dataviz_AI_Data_Fetcher` and helpers) → optional LLM summarization (`Dataviz_AI_API_Client` → OpenAI) → JSON response → UI.

At no point does the LLM choose tools; all tool selection and execution is rule‑based PHP.

### 2. Intent Classification & Tool Selection

- **Intent classifier**: `Dataviz_AI_Intent_Classifier`.
- Responsibilities:
  - Decide if a question **requires WooCommerce data**.
  - Extract:
    - **Entity**: `orders`, `products`, `customers`, `categories`, `tags`, `coupons`, `refunds`, `stock`, `inventory`.
    - **Query type**: `list`, `statistics`, `sample`, `by_period`.
    - **Filters**: `date_from`, `date_to`, `status`, `limit`, `group_by`, `stock_status` (`outofstock`), `stock_threshold`, etc.
  - Build **tool call arrays**, never passed to the LLM as tools:
    - `get_order_statistics` with a flat filters array.
    - `get_woocommerce_data` with `{ entity_type, query_type, filters }`.
- Special patterns:
  - **Revenue/sales totals** → `orders + statistics`.
  - **Calendar expressions**:
    - Relative: “this month”, “last month”, “last year”, “in the last 30 days”.
    - Explicit: “December 1987”, “the month of December 1987”.
  - **Stock / inventory**:
    - `low stock`, `running low` → `entity_type = stock` (low‑stock path).
    - `out of stock` → `entity_type = stock`, `filters.stock_status = 'outofstock'`.
    - `inventory`, `current inventory` → `entity_type = inventory`.

### 3. Tool Execution & Data Fetching

All tools ultimately execute inside `Dataviz_AI_AJAX_Handler` via `execute_tool()` and `Dataviz_AI_Data_Fetcher`:

- **Orders**:
  - `get_order_statistics($args)`:
    - Computes `summary` (`total_orders`, `total_revenue`, `avg_order_value`, etc.).
    - Optionally `status_breakdown`, `category_breakdown`, `daily_trend`.
    - Always returns a `date_range` (`from`, `to`) reflecting the applied filters.
  - `get_orders_by_period($period, $filters)`, `get_recent_orders($args)`, `get_sampled_orders($filters)`.
- **Products / customers / categories / tags / coupons / refunds**:
  - Dedicated helpers on `Dataviz_AI_Data_Fetcher`.
- **Stock / inventory**:
  - `get_low_stock_products($threshold)`:
    - `stock_status = instock`, quantity `< threshold`.
  - `get_all_inventory_products($filters)`:
    - All published products with stock fields (`stock_quantity`, `stock_status`, `manage_stock`, `backorders`).
  - `get_out_of_stock_products()`:
    - `stock_status = outofstock` (fully out‑of‑stock items).

### 4. Non‑Streaming Analysis Flow (`handle_smart_analysis`)

1. UI calls `action=dataviz_ai_smart_analysis` with `question`.
2. `Dataviz_AI_AJAX_Handler::handle_smart_analysis()`:
   - Uses `Dataviz_AI_Intent_Classifier::classify_intent_and_get_tools($question)` to get a list of tool call arrays.
   - If **no tools** but `question_requires_data()` is true → returns a deterministic error message (no LLM call).
3. For each tool call:
   - Validates arguments (`validate_tool_arguments()`).
   - Executes the underlying function in PHP with try/catch and WP_Error normalization.
4. Builds `results_for_prompt`:
   - Each entry: `{ tool, arguments, result }`.
5. **Deterministic short‑circuits** (no LLM):
   - Single orders list result with `orders: []`:
     - Answers: “There are no orders [for the requested period / from X to Y].”
   - Single `get_order_statistics` result:
     - Uses `summary.total_revenue`, `summary.total_orders`, `date_range` to answer:
       - “The total revenue generated from [date_from] to [date_to] is [amount]. There are N orders in this period.”
6. If no short‑circuit applies:
   - Builds OpenAI messages:
     - System (`system_analyst` template).
     - User question.
     - Assistant “data context” message with `wp_json_encode($results_for_prompt)`.
     - Final user message instructing the LLM to answer *only* from the provided data and explain empty results / errors as needed.
   - Calls `Dataviz_AI_API_Client::send_openai_chat($messages)` and returns the LLM’s text.

### 5. Streaming Analysis Flow (`handle_streaming_analysis`)

1. UI calls `action=dataviz_ai_streaming_analysis` with `question`.
2. `Dataviz_AI_AJAX_Handler::handle_streaming_analysis()`:
   - Same intent classification as non‑stream.
   - Builds tool calls and executes them in PHP.
   - Constructs:
     - `assistant_tool_calls` (for OpenAI compatibility, though tools are not actually invoked by the LLM).
     - `tool_results_messages` (role=`tool`, JSON content).
     - `tool_results_for_frontend` (used by the frontend for charts).
3. Sends a **metadata chunk** to the frontend with `tool_data` (orders, inventory, statistics) before any text.
4. **Deterministic streaming paths (no LLM)**:
   - Empty orders:
     - Single orders list with `orders: []` → streams “There are no orders …” and ends.
   - Revenue questions:
     - If question mentions `revenue/sales/turnover` and a `get_order_statistics` result is present:
       - Builds the same deterministic revenue + order count message as non‑stream.
       - Streams it, saves to history, and ends without calling `send_openai_chat_stream()`.
   - Simple “how many” questions:
     - For `how many customers` / `how many orders`, extracts totals from the `summary` section and streams a direct numeric answer.
5. If no deterministic path applies:
   - Builds a combined prompt from:
     - `data_analysis`, `error_handling`, `chart_request`, and `empty_data` templates.
   - Calls `send_openai_chat_stream()` to stream a narrative answer that references the tool data.

### 6. How to Debug Common Data Issues

- **No orders shown for a date range**:
  - Check what `extract_filters()` computed (`date_from`, `date_to`) and what `get_order_statistics()` is called with.
  - Confirm that `get_orders_for_range()` is using the same dates.
- **Revenue looks wrong**:
  - Inspect `summary.total_revenue` from `get_order_statistics()` before it is formatted.
  - Verify that the question’s date phrasing (e.g. “December 1987”) is parsed into the expected `date_from` / `date_to`.
- **Stock / inventory questions**:
  - “Low stock” → `get_low_stock_products(threshold)` → only `instock` items below threshold.
  - “Out of stock” → `get_out_of_stock_products()` → only `outofstock` items.
  - “Inventory” / “current inventory” → `get_all_inventory_products()` → all published products with stock information.

