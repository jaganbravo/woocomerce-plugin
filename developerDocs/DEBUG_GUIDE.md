# Debug Guide: Tracing the "How many completed orders?" Issue

## Quick Start

1. **Enable debug logging** (if not already enabled):
   ```bash
   # In Docker, check wp-config.php or set in environment
   # Should have:
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Ask the question**: "How many orders are currently completed?"

3. **Check the logs**:
   ```bash
   # In Docker (container name is 'wp_app')
   docker exec -it wp_app tail -f /var/www/html/wp-content/debug.log | grep "\[Dataviz AI\]"
   
   # Or check the file directly (from host machine)
   tail -f docker/wordpress/wp-content/debug.log | grep "\[Dataviz AI\]"
   
   # Or view all recent logs
   docker exec -it wp_app tail -n 100 /var/www/html/wp-content/debug.log | grep "\[Dataviz AI\]"
   ```

## What to Look For in Logs

The logs will show the complete flow. Look for these entries in order:

### 1. Tool Execution
```
[Dataviz AI] Tool executed: get_order_statistics with args: {...}
```
**What to check**: Is the correct tool being called? Are the arguments correct?

### 2. Raw Tool Result
```
[Dataviz AI] Raw tool result (before enhancement): status_breakdown=[...]
```
**What to check**: Does `status_breakdown` contain the data? Is there a "completed" entry?

### 3. Enhancement Start
```
[Dataviz AI] Enhancement: Starting for question: "How many orders are currently completed?"
```
**What to check**: Is the enhancement function being called?

### 4. Status Detection
```
[Dataviz AI] Enhancement: Detected status: completed (from question: "...")
```
**What to check**: 
- ✅ If it says `completed` → Good, status was detected
- ❌ If it says `NONE` → Problem: status not detected from question

### 5. Enhanced Fields Added
```
[Dataviz AI] Enhancement: Added fields - completed_orders_count=15, requested_status_count={...}
```
**What to check**: 
- ✅ If you see this → Enhancement worked, fields were added
- ❌ If you see "SKIPPED" → Check why (no status detected, or no status_breakdown)

### 6. Enhanced Tool Result
```
[Dataviz AI] Enhanced tool result: {"completed_orders_count":15,"requested_status_count":{...}}
```
**What to check**: Are the enhanced fields present? What are their values?

### 7. Tool Result JSON Sent to LLM
```
[Dataviz AI] Sending tool result to LLM (tool: get_order_statistics, JSON length: 1234): {...}
```
**What to check**: 
- Does the JSON preview show `completed_orders_count`?
- Does it show `requested_status_count`?
- Is the JSON valid?

### 8. Complete Messages Array
```
[Dataviz AI] Complete messages array (5 messages): [...]
```
**What to check**: 
- How many messages are in the array?
- Is there a tool message with `enhanced_fields`?
- What does the final prompt look like?

## Common Issues & Solutions

### Issue 1: Status Not Detected
**Symptom**: Log shows `Detected status: NONE`
**Cause**: Question doesn't match keywords
**Solution**: Check the question wording. The enhancement looks for: "completed", "complete", "finished", "fulfilled"

**Fix**: Update `enhance_order_statistics_for_status_question()` to add more keywords or improve detection logic.

### Issue 2: No status_breakdown in Result
**Symptom**: Log shows `Enhancement: SKIPPED - no status_breakdown in result`
**Cause**: `get_order_statistics()` didn't return `status_breakdown`
**Solution**: Check `get_order_statistics()` - is it returning the correct structure?

**Debug**: Add logging in `get_order_statistics()` to see what it's returning.

### Issue 3: Enhanced Fields Not in JSON
**Symptom**: Enhancement logs show fields added, but JSON sent to LLM doesn't contain them
**Cause**: JSON encoding issue or fields overwritten
**Solution**: Check if `wp_json_encode()` is working correctly

**Debug**: Log `$tool_result` right before `wp_json_encode()` to see the actual array.

### Issue 4: LLM Still Doesn't Use the Number
**Symptom**: All logs look correct, but LLM response is still "There are currently completed orders"
**Possible Causes**:
1. **Final prompt not clear enough** - LLM is ignoring instructions
2. **Message ordering** - Tool result comes after prompt, LLM doesn't see it
3. **Model issue** - gpt-4o-mini might not be following instructions well

**Solutions**:
- Try a stronger model (gpt-4o instead of gpt-4o-mini)
- Move the tool result message to come BEFORE the final prompt
- Make the final prompt even more explicit
- Add the count directly in the final prompt text

## Step-by-Step Debugging Process

### Step 1: Verify Enhancement is Running
```
grep "Enhancement: Starting" debug.log
```
If you don't see this, the enhancement function isn't being called.

### Step 2: Verify Status Detection
```
grep "Detected status" debug.log
```
Should show `completed`. If it shows `NONE`, that's your problem.

### Step 3: Verify Fields Are Added
```
grep "Added fields" debug.log
```
Should show `completed_orders_count=15` (or whatever the actual count is).

### Step 4: Verify JSON Contains Fields
```
grep "Sending tool result to LLM" debug.log | grep "completed_orders_count"
```
If this doesn't match, the fields aren't in the JSON being sent.

### Step 5: Verify Messages Array
```
grep "Complete messages array" debug.log -A 20
```
Check if the tool message has `enhanced_fields` in the summary.

## Quick Test Script

Run this to test the enhancement function directly:

```php
// Add to a test file or run in WordPress CLI
$question = "How many orders are currently completed?";
$tool_result = array(
    'summary' => array('total_orders' => 20),
    'status_breakdown' => array(
        array('status' => 'completed', 'count' => 15),
        array('status' => 'pending', 'count' => 5),
    ),
);

// Simulate the enhancement
// (Copy the function logic here and test)
```

## Next Steps After Debugging

Once you identify where the flow breaks:

1. **If enhancement doesn't run**: Check why `get_order_statistics` result isn't being enhanced
2. **If status not detected**: Improve keyword matching or add more keywords
3. **If fields not in JSON**: Check JSON encoding
4. **If LLM ignores fields**: Try stronger prompt or different model

## Architecture Reference

See `ARCHITECTURE_DEBUG.md` for the complete flow diagram and file locations.

