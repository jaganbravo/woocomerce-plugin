# Testing Strategy for Dataviz AI WooCommerce Plugin

## 🎯 Testing Overview

### Current Status
- ✅ Manual testing in Docker environment
- ❌ No automated tests yet
- ❌ No agentic AI testing yet

---

## 📋 Manual Testing Checklist

### 1. Basic Functionality Tests

#### Test 1: Plugin Activation
- [ ] Install plugin
- [ ] Activate plugin
- [ ] Check for errors in debug log
- [ ] Verify database tables created (feature_requests, chat_history)

#### Test 2: Settings Configuration
- [ ] Navigate to Settings → Dataviz AI
- [ ] Enter OpenAI API key
- [ ] Save settings
- [ ] Verify API key is stored (check database)
- [ ] Test API connection

#### Test 3: Chat Interface
- [ ] Open Dataviz AI admin page
- [ ] Verify chat interface loads
- [ ] Check for JavaScript errors in console
- [ ] Verify chat input is functional

### 2. Core Feature Tests

#### Test 4: Supported Data Types
Test each supported entity type:

**Orders:**
- [ ] "Show me recent orders"
- [ ] "What's my total revenue this month?"
- [ ] "Show me orders by status"
- [ ] "What's my average order value?"

**Products:**
- [ ] "Show me top 5 products"
- [ ] "What products are low in stock?"
- [ ] "Show me products in Electronics category"

**Customers:**
- [ ] "Who are my top customers?"
- [ ] "How many customers do I have?"
- [ ] "Show me customer summary"

**Categories:**
- [ ] "Show me all product categories"
- [ ] "How many products in each category?"

**Tags:**
- [ ] "Show me all product tags"

**Coupons:**
- [ ] "Show me all available coupons"
- [ ] "Which coupons were used most?"

**Refunds:**
- [ ] "Show me refunds this month"

**Stock:**
- [ ] "Show me products with low stock"
- [ ] "What products have less than 10 in stock?"

### 3. Error Handling Tests

#### Test 5: Unsupported Features
- [ ] "Show me data for sales commission" → Should prompt for feature request
- [ ] "Show me product reviews" → Should prompt for feature request
- [ ] "Show me shipping data" → Should prompt for feature request
- [ ] "Show me tax information" → Should prompt for feature request

#### Test 6: Feature Request Flow
- [ ] Ask for unsupported feature
- [ ] Verify error message with "can_submit_request: true"
- [ ] Say "yes" to submit feature request
- [ ] Verify request is stored in database
- [ ] Verify email notification sent (if SMTP configured)
- [ ] Check feature_requests table for new entry

#### Test 7: Invalid API Key
- [ ] Enter invalid API key
- [ ] Ask a question
- [ ] Verify error message is user-friendly
- [ ] Check debug logs for errors

#### Test 8: Empty Data
- [ ] Test with store that has no orders
- [ ] Test with store that has no products
- [ ] Verify graceful handling of empty results

### 4. Edge Cases

#### Test 9: Large Datasets
- [ ] Test with 1000+ orders
- [ ] Verify statistics query works
- [ ] Verify sampling works (max 500)
- [ ] Check response time

#### Test 10: Special Characters
- [ ] Test questions with special characters
- [ ] Test product names with special characters
- [ ] Verify proper escaping

#### Test 11: Concurrent Requests
- [ ] Open multiple chat windows
- [ ] Send questions simultaneously
- [ ] Verify no conflicts

### 5. UI/UX Tests

#### Test 12: Chat Interface
- [ ] Test message sending
- [ ] Test streaming responses
- [ ] Test stop button (if implemented)
- [ ] Test chat history (if implemented)
- [ ] Test responsive design (mobile/tablet)

#### Test 13: Visualizations
- [ ] Test chart rendering for orders
- [ ] Test chart rendering for products
- [ ] Verify charts don't show for unsupported types

---

## 🤖 Agentic AI Testing

### What is Agentic AI Testing?

Agentic AI testing uses AI agents that can:
- Automatically explore the plugin
- Test various scenarios
- Find edge cases
- Generate test reports
- Learn from previous tests

### Should You Use Agentic AI?

#### ✅ Pros:
1. **Comprehensive Coverage** - Tests many scenarios automatically
2. **Time Saving** - Runs tests 24/7
3. **Edge Case Discovery** - Finds bugs you might miss
4. **Regression Testing** - Catches regressions automatically
5. **Natural Language Testing** - Tests like a real user

#### ❌ Cons:
1. **Setup Complexity** - Requires infrastructure
2. **Cost** - API costs for AI agents
3. **False Positives** - May flag non-issues
4. **Maintenance** - Need to maintain test agents
5. **Overkill for MVP** - Might be too complex for initial launch

### Recommendation: **Not for MVP, Consider Later**

**For MVP:**
- Focus on manual testing
- Use simple automated tests (PHPUnit)
- Test critical paths manually

**Post-MVP:**
- Consider agentic AI for regression testing
- Use for continuous testing
- Helpful for large-scale testing

---

## 🧪 Automated Testing Options

### Option 1: PHPUnit (Recommended for MVP)

**What it tests:**
- Unit tests for individual functions
- Integration tests for database operations
- API client tests

**Example Test:**
```php
class Test_Feature_Requests extends WP_UnitTestCase {
    public function test_submit_request() {
        $feature_requests = new Dataviz_AI_Feature_Requests();
        $request_id = $feature_requests->submit_request('commission', 1, 'Test description');
        $this->assertNotFalse($request_id);
    }
}
```

**Setup:**
```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Run tests
./vendor/bin/phpunit
```

### Option 2: Playwright/Cypress (E2E Testing)

**What it tests:**
- Full user flows
- Browser interactions
- Chat interface testing

**Example:**
```javascript
test('submit feature request', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=dataviz-ai');
  await page.fill('input[type="text"]', 'show me commission data');
  await page.click('button[type="submit"]');
  // Wait for error response
  await page.click('text=yes');
  // Verify success message
});
```

### Option 3: Agentic AI Testing (Post-MVP)

**Tools:**
- **Codium AI** - AI-powered test generation
- **TestGen** - Automated test creation
- **Custom Agent** - Build your own with OpenAI API

**Example Flow:**
1. Agent explores plugin
2. Tests various questions
3. Verifies responses
4. Reports issues

---

## 📝 Testing Plan for MVP Launch

### Phase 1: Pre-Launch Testing (This Week)

**Priority: CRITICAL**
- [ ] Manual testing of all supported entity types
- [ ] Test feature request flow
- [ ] Test error handling
- [ ] Test with empty data
- [ ] Test API key validation

**Time:** 4-6 hours

### Phase 2: Beta Testing (1-2 weeks)

**Recruit 5-10 beta testers:**
- [ ] Friends/colleagues with WooCommerce stores
- [ ] WooCommerce community members
- [ ] Collect feedback
- [ ] Fix critical bugs

### Phase 3: Post-Launch Monitoring

**Track:**
- [ ] Error logs
- [ ] User feedback
- [ ] Support requests
- [ ] Feature requests

---

## 🛠️ Quick Testing Setup

### 1. Enable Debug Mode

**wp-config.php:**
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 2. Check Debug Logs

```bash
# View logs
docker compose exec wp_app tail -f /var/www/html/wp-content/debug.log

# Filter for plugin errors
docker compose exec wp_app grep "Dataviz AI" /var/www/html/wp-content/debug.log
```

### 3. Test Checklist Script

Create a simple test script:

```php
// test-plugin.php
require_once 'wp-load.php';

echo "Testing Dataviz AI Plugin...\n";

// Test 1: Plugin active
if (is_plugin_active('dataviz-ai-woocommerce-plugin/dataviz-ai-woocommerce.php')) {
    echo "✅ Plugin is active\n";
} else {
    echo "❌ Plugin is not active\n";
}

// Test 2: Database tables exist
global $wpdb;
$tables = ['dataviz_ai_feature_requests', 'dataviz_ai_chat_history'];
foreach ($tables as $table) {
    $table_name = $wpdb->prefix . $table;
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo "✅ Table $table exists\n";
    } else {
        echo "❌ Table $table missing\n";
    }
}

// Test 3: API key configured
$api_key = get_option('dataviz_ai_openai_api_key');
if (!empty($api_key)) {
    echo "✅ API key configured\n";
} else {
    echo "❌ API key not configured\n";
}
```

---

## 🎯 Recommended Testing Approach for MVP

### Week 1: Manual Testing
1. **Day 1-2:** Test all supported entity types
2. **Day 3:** Test error handling and feature requests
3. **Day 4:** Test edge cases and large datasets
4. **Day 5:** Fix critical bugs found

### Week 2: Beta Testing
1. Recruit 5-10 beta testers
2. Collect feedback
3. Fix reported issues

### Week 3: Final Testing
1. Regression testing
2. Performance testing
3. Security testing
4. Launch preparation

---

## 🤖 Agentic AI Testing (Future)

### When to Consider:
- ✅ After MVP launch
- ✅ When you have 100+ users
- ✅ When adding complex features
- ✅ For regression testing

### How to Implement:

**Option 1: Custom Agent**
```python
# test_agent.py
import openai

def test_plugin():
    agent = openai.ChatCompletion.create(
        model="gpt-4",
        messages=[{
            "role": "system",
            "content": "You are a testing agent. Test the WooCommerce plugin by asking various questions and verifying responses."
        }]
    )
    # Agent tests plugin automatically
```

**Option 2: Use Testing Tools**
- **Codium AI** - AI test generation
- **TestGen** - Automated testing
- **Playwright + AI** - E2E testing with AI

---

## ✅ Final Recommendation

**For MVP Launch:**
1. ✅ **Manual Testing** - Do this first (4-6 hours)
2. ✅ **Simple PHPUnit Tests** - Add basic tests (2-3 hours)
3. ❌ **Agentic AI** - Skip for now, consider later

**Post-MVP:**
1. Add comprehensive PHPUnit tests
2. Add E2E tests (Playwright)
3. Consider agentic AI for regression testing

---

## 📊 Testing Metrics

Track these:
- **Test Coverage:** % of code tested
- **Bugs Found:** Number of issues discovered
- **Test Execution Time:** How long tests take
- **Pass Rate:** % of tests passing

---

**Start with manual testing, then add automation gradually!** 🚀

