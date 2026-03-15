## Long-term Maintenance & Refactor Plan

### 1. Generic Query & Aggregation Model

- **Goal**: Move from many bespoke methods to a small, generic query/aggregation API.
- **Direction**:
  - Define a shared query shape for data questions:
    - `entity_type` (orders, products, customers, inventory, etc.)
    - `query_type` (`list`, `statistics`, `sample`, `by_period`)
    - Optional: `group_by`, `metrics`, `order_by`, `order`, `limit`, `filters` (date range, status, etc.).
  - Implement this shape on top of a small set of reusable primitives:
    - e.g. `get_orders_for_range()`, `aggregate_orders_by_customer()`, `aggregate_orders_by_period()`.
- **Why**:
  - Keeps the data layer small and composable.
  - Reduces the need to add one method per analytics question.

### 2. Consolidate Order & Customer Aggregation

- **Current state**:
  - `get_order_statistics()` computes order-level aggregates.
  - `get_customer_statistics()` (new) computes customer-level aggregates from orders.
- **Improvements**:
  - Extract shared logic (date filter building, base `wc_get_orders` call, safety checks) into internal helpers.
  - Consider a unified aggregator that can:
    - `group_by = order`, `customer`, `status`, `day`, `month`, etc.
    - Compute metrics: `total_revenue`, `order_count`, `total_spent`, `avg_order_value`.
- **Implemented**: Sales by product category is implemented via `group_by=category` on the generic order aggregator (data fetcher primitives: `build_order_query_args`, `get_orders_for_range`, `aggregate_orders`). No dedicated "sales by category" endpoint; the same query shape is used with `entity_type=orders`, `query_type=statistics`, `filters.group_by=category`.

### 3. Intent Classifier → Query Shape Mapping

- **Goal**: Keep the classifier focused on mapping text → generic query shape, not specific PHP methods.
- **Actions**:
  - Continue to encode patterns like “top customers this year” as:
    - `entity_type = customers`
    - `query_type = statistics`
    - `group_by = customer`
    - `sort_by = total_spent`
    - `limit = N`
    - `date_from` / `date_to` from natural language.
  - Avoid adding classifier branches that directly reference implementation details (e.g. specific method names).

### 4. Tool & Validation Layer Simplification

- **Current**:
  - `get_woocommerce_data` + validation layer (`validate_tool_arguments`, `sanitize_filters`).
- **Future direction**:
  - Align validation rules with the generic query schema (see section 1).
  - Clearly document which filters are supported per entity + query_type.
  - Keep the tool surface small; prefer evolving the query schema over adding new top-level tools.

### 5. Multi-Entity Queries

- **Current**:
  - Classifier can detect multiple entities and build multiple tool calls.
  - Response merging is still mostly handled by the LLM at the narrative level.
- **Future**:
  - Define a lightweight merging strategy for common multi-entity cases (e.g. orders + customers).
  - Ensure the frontend can consume multiple structured result types (orders, customers, inventory) for richer visualizations.

### 6. Test Coverage & Synthetic Questions

- Add targeted tests for:
  - “Top customers” variants (this year, last month, in the last 90 days, etc.).
  - Edge cases: no orders in range, only guest orders, customers with 1 order vs many.
- Keep `tests/VALIDATION_IMPROVEMENTS.md` and related docs in sync with:
  - New filters (`sort_by`, `group_by`, `min_orders`).
  - New aggregation behaviors.

### 7. Performance & Scaling Considerations

- Monitor performance for large stores:
  - `wc_get_orders` with `limit = -1` can be expensive for very large datasets.
  - Consider:
    - Adding caps or warning thresholds.
    - Introducing approximate / sampled aggregation for “top X” style queries.

### 8. Error Messaging & UX Consistency

- Standardize “no data” responses:
  - Always include the resolved date range.
  - Avoid partial phrases when metrics are zero or missing.
- Ensure the AI explanation layer is aware of:
  - When results are “no data” vs “unsupported feature” vs “error”.

