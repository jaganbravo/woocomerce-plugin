# WooCommerce AI Analytics Plugin - Architecture & Debug Guide

## Complete Request Flow

```
┌─────────────────┐
│   Frontend      │
│  (admin.js)     │
└────────┬────────┘
         │
         │ 1. User types question
         │ 2. AJAX POST to wp-admin/admin-ajax.php
         │    - action: 'dataviz_ai_analyze'
         │    - question: "How many completed orders?"
         │    - stream: true
         │    - session_id: "abc123"
         │
         ▼
┌─────────────────────────────────────┐
│   WordPress AJAX Handler            │
│   class-dataviz-ai-ajax-handler.php │
│   handle_analysis_request()         │
└────────┬────────────────────────────┘
         │
         │ 3. Save user message to chat history
         │ 4. Call handle_streaming_analysis()
         │
         ▼
┌─────────────────────────────────────┐
│   Streaming Analysis Handler        │
│   handle_streaming_analysis()       │
└────────┬────────────────────────────┘
         │
         │ 5. Build initial messages array
         │    - System prompt (instructions)
         │    - User question
         │ 6. Get available tools (get_order_statistics, etc.)
         │ 7. Call OpenAI API (first time)
         │    - Model: gpt-4o-mini
         │    - Tools: available tools
         │    - Tool choice: 'auto'
         │
         ▼
┌─────────────────────────────────────┐
│   OpenAI API (First Call)           │
│   send_openai_chat()                 │
└────────┬────────────────────────────┘
         │
         │ 8. OpenAI responds with:
         │    - Either: tool_calls array (wants to call tools)
         │    - Or: content (text response - should not happen for data questions)
         │
         ▼
┌─────────────────────────────────────┐
│   Tool Call Detection               │
│   - Check if LLM called tools        │
│   - If not, auto-detect based on    │
│     question keywords                │
└────────┬────────────────────────────┘
         │
         │ 9. If tool_calls exist (or auto-detected):
         │    - Create assistant message with tool_calls
         │    - Execute each tool
         │
         ▼
┌─────────────────────────────────────┐
│   Tool Execution                    │
│   execute_tool()                    │
│   └─> get_order_statistics()        │
└────────┬────────────────────────────┘
         │
         │ 10. Tool returns data:
         │     {
         │       "summary": {...},
         │       "status_breakdown": [
         │         {"status": "completed", "count": 15},
         │         {"status": "pending", "count": 3}
         │       ],
         │       ...
         │     }
         │
         ▼
┌─────────────────────────────────────┐
│   Data Enhancement                  │
│   enhance_order_statistics_for_      │
│   status_question()                  │
└────────┬────────────────────────────┘
         │
         │ 11. Detect status from question
         │ 12. Extract count from status_breakdown
         │ 13. Add explicit fields:
         │     {
         │       "completed_orders_count": 15,
         │       "requested_status_count": {
         │         "status": "completed",
         │         "count": 15,
         │         "message": "There are 15 completed orders."
         │       },
         │       ... (original data)
         │     }
         │
         ▼
┌─────────────────────────────────────┐
│   Build Tool Messages               │
│   - Add assistant message with      │
│     tool_calls                      │
│   - Add tool messages with results  │
└────────┬────────────────────────────┘
         │
         │ 14. Messages array now contains:
         │     [
         │       {role: "system", content: "..."},
         │       {role: "user", content: "How many..."},
         │       {role: "assistant", tool_calls: [...]},
         │       {role: "tool", tool_call_id: "...", content: "{...}"},
         │       {role: "user", content: "final_prompt"}
         │     ]
         │
         ▼
┌─────────────────────────────────────┐
│   OpenAI API (Second Call)          │
│   send_openai_chat_stream()         │
│   - Streaming response               │
└────────┬────────────────────────────┘
         │
         │ 15. OpenAI streams response chunks
         │ 16. Each chunk processed by callback
         │ 17. Chunks sent to frontend via SSE
         │
         ▼
┌─────────────────────────────────────┐
│   Frontend (admin.js)               │
│   - Receives SSE chunks             │
│   - Appends to AI message           │
│   - Auto-scrolls                    │
└─────────────────────────────────────┘
```

## Key Files & Responsibilities

### 1. Frontend (`admin/js/admin.js`)
- **Lines 509-573**: Chat form submission
- **Lines 573-650**: Fetch API with streaming
- **Responsibility**: Send question, receive streaming response, update UI

### 2. AJAX Handler (`includes/class-dataviz-ai-ajax-handler.php`)
- **Lines 78-140**: `handle_analysis_request()` - Entry point
- **Lines 137-500**: `handle_streaming_analysis()` - Main orchestration
- **Lines 425-470**: Tool execution loop
- **Lines 437-441**: Data enhancement (NEW - where we add explicit counts)
- **Lines 1316-1370**: `enhance_order_statistics_for_status_question()` - Status detection & enhancement
- **Lines 1236-1315**: `execute_tool()` - Tool routing
- **Responsibility**: Orchestrate LLM calls, tool execution, response streaming

### 3. Data Fetcher (`includes/class-dataviz-ai-data-fetcher.php`)
- **Lines 176-270**: `get_order_statistics()` - Query database, return structured data
- **Responsibility**: Fetch WooCommerce data from database

### 4. API Client (`includes/class-dataviz-ai-api-client.php`)
- **Lines 100-240**: `send_openai_chat()` - Non-streaming API calls
- **Lines 241-392**: `send_openai_chat_stream()` - Streaming API calls
- **Responsibility**: Communicate with OpenAI API

## Critical Points Where Things Can Go Wrong

### Point 1: Tool Call Detection (Line 340-365)
**What happens**: LLM might return text instead of tool_calls
**Debug**: Check if `$tool_calls` is empty, then auto-detect kicks in
**Log location**: Line 349

### Point 2: Tool Execution (Line 425)
**What happens**: Tool might return error or wrong data structure
**Debug**: Check `$tool_result` after `execute_tool()`
**Log location**: Need to add

### Point 3: Data Enhancement (Line 440)
**What happens**: Status might not be detected, or count not extracted
**Debug**: Check if `completed_orders_count` exists in final `$tool_result`
**Log location**: Need to add

### Point 4: Tool Result JSON (Line 468)
**What happens**: JSON encoding might fail, or structure wrong
**Debug**: Check what's actually in `wp_json_encode($tool_result)`
**Log location**: Need to add

### Point 5: Final Prompt (Line 478-499)
**What happens**: LLM might ignore instructions
**Debug**: Check what prompt is sent to OpenAI
**Log location**: Need to add

### Point 6: LLM Response (Line 500-520)
**What happens**: LLM might not extract number from data
**Debug**: Check what response chunks are received
**Log location**: Streaming callback

## How to Debug This Issue

### Step 1: Enable Debug Logging
Add `WP_DEBUG` and `WP_DEBUG_LOG` to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Step 2: Add Strategic Log Points
We'll add logging at each critical point to see:
1. What question was asked
2. What tool was called
3. What data was returned
4. What enhancement was applied
5. What JSON was sent to LLM
6. What final prompt was used
7. What LLM responded

### Step 3: Check Logs
After asking "How many completed orders?", check:
- `/wp-content/debug.log` (or Docker logs)
- Look for `[Dataviz AI]` entries
- Trace the flow from question → tool → enhancement → LLM

### Step 4: Verify Data Structure
The enhanced tool result should have:
```json
{
  "completed_orders_count": 15,
  "requested_status_count": {
    "status": "completed",
    "count": 15,
    "message": "There are 15 completed orders."
  },
  "status_breakdown": [...],
  ...
}
```

If this structure is correct but LLM still doesn't use it, the issue is in:
- The final prompt (not clear enough)
- The LLM model (not following instructions)
- The message ordering (tool result before prompt)

## Next Steps

1. Add comprehensive debug logging
2. Test with a question
3. Review logs to see where the flow breaks
4. Fix the specific point of failure

