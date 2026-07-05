# Codex Suggestions: Reliable NLP for Store Compass

## Why current behavior feels brittle

Fixing one phrase at a time (`stock level`, `inventory`, etc.) is not scalable.  
The solution is to treat NLP as a **front-end to a deterministic query engine**, not as free-form logic.

---

## Target Architecture

### 1) Intent Layer (LLM + strict schema)
Input: user question  
Output: validated JSON intent

Required fields:
- `entity`: `orders|products|customers|inventory|categories|coupons|refunds`
- `operation`: `list|count|sum|avg|group_by|trend|compare`
- `metrics`: array (e.g. `revenue`, `orders_count`)
- `dimensions`: array (e.g. `status`, `category`, `date`)
- `filters`: object (`date_from`, `date_to`, `status`, `category`, `stock_mode`, `limit`)
- `scope`: `all|top_n|low_stock|out_of_stock`
- `confidence`: `high|med|low`
- `needs_clarification`: boolean
- `clarifying_question`: string|null

Rule:
- If schema validation fails, **do not execute tools**; ask a clarifying question.

### 2) Deterministic Execution Layer (PHP)
- Map intent -> fixed tool calls (no phrase guessing).
- Normalize ambiguous values (`limit=-1`, invalid dates, unknown statuses).
- Return typed payloads:
  - `{ rows, totals, metadata, warnings }`

### 3) Response Layer
- Prefer deterministic templates for critical entities (`inventory`, `orders`, `revenue`).
- LLM is only for phrasing/summarization of structured payload.
- Prevent contradictions:
  - Never say “all products” when payload has one row unless `total=1`.

---

## 2-Week Migration Plan

### Phase A (Day 1-2): Schema + Router
- Freeze one intent schema.
- Build one routing table:
  - `(entity, operation, scope) -> executor`
- Remove phrase-specific execution logic.

### Phase B (Day 3-5): Inventory/Stock End-to-End
- Introduce `stock_mode`: `all|low|out`.
- Ensure “all inventory” always calls `get_all_inventory_products`.
- Fix `limit=-1` semantics (never clamped to 1).
- Add deterministic inventory formatter.

### Phase C (Day 6-8): Golden Dataset + CI
Create `tests/golden-queries.json`:
- 40 inventory/stock
- 40 orders/revenue
- 30 date edge-cases
- 20 ambiguous/unsupported

Assertions:
- intent shape
- tool routing correctness
- minimum expected rows
- contradiction checks (“all” vs returned count)

### Phase D (Day 9-10): Clarification UX
- If confidence low or required filter missing:
  - ask a single clarifying question
  - don’t execute data query yet
- Use next turn response to complete intent.

### Phase E (Day 11-14): Controlled Expansion
- Add compare/trend operations via explicit intent fields.
- Keep unsupported requests routed to feature-request flow.

---

## Non-Negotiable Guardrails

- No silent fallback to LLM guessing.
- No implicit semantic changes (`stock` must not default to `low` unless asked).
- No untyped tool payloads.
- One centralized limit policy (`-1` handling).

---

## Core Decision Table

- `entity=inventory, operation=list, scope=all`  
  -> `get_all_inventory_products(limit=-1)`

- `entity=inventory, operation=list, scope=low_stock`  
  -> `get_low_stock_products(threshold)`

- `entity=inventory, operation=list, scope=out_of_stock`  
  -> `get_out_of_stock_products()`

If scope missing:
- contains `all/every/full` -> `all`
- contains `low/running low` -> `low_stock`
- contains `out of stock` -> `out_of_stock`
- otherwise ask: “Do you want all inventory, low-stock only, or out-of-stock only?”

---

## Success Criteria

- 10 paraphrases of the same question produce same intent + same result.
- “All inventory” never returns one row unless total products = 1.
- CI catches regressions before manual QA.
- Prompt edits become rare; behavior changes through schema/router/tests.