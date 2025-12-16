# AI Chat Test Agent

Automated testing for the Dataviz AI WooCommerce plugin using Playwright and OpenAI.

## Features

- 🤖 **AI-Generated Test Questions**: Uses GPT to generate diverse, natural test questions
- ✅ **AI-Powered Verification**: Uses AI to verify if responses are correct
- 📊 **Chart Detection**: Automatically detects if charts are displayed
- 🔄 **Conversational Testing**: Tests the full chat flow
- 📈 **Test Reports**: Generates detailed test summaries

## Setup

### 1. Install Dependencies

```bash
cd tests
npm install
```

### 2. Install Playwright Browsers

```bash
npx playwright install chromium
```

### 3. Configure Environment

Create a `.env` file in the `tests` directory:

```env
OPENAI_API_KEY=sk-your-openai-api-key
PLUGIN_URL=http://localhost:8080/wp-admin/admin.php?page=dataviz-ai-woocommerce
WP_ADMIN_USER=admin
WP_ADMIN_PASS=your-password
```

## Usage

### Run Tests (with browser visible)

```bash
npm test
```

### Run Tests (headless mode)

```bash
npm run test:headless
```

Or set environment variable:

```bash
HEADLESS=true npm test
```

## What It Tests

The agent automatically tests:

1. **Basic Questions**
   - "Show me inventory in a pie chart"
   - "What are my top selling products?"
   - "How many orders do I have?"

2. **Chart Requests**
   - Verifies charts are displayed
   - Checks chart rendering

3. **Data Queries**
   - Products, orders, customers, inventory
   - Statistics and analytics

4. **Edge Cases**
   - Feature requests for unsupported features
   - Invalid questions
   - Empty data scenarios

## Test Output

The agent provides:
- ✅ Pass/fail for each test
- 📊 Chart detection status
- 📝 Response verification
- 📈 Summary statistics

Example output:
```
📝 Test 1: "Show me inventory in a pie chart"
✅ PASSED: Response contains data and chart is displayed
   📊 Chart displayed

📝 Test 2: "What are my top selling products?"
✅ PASSED: Response contains relevant product data

📊 TEST SUMMARY
Total Tests: 15
✅ Passed: 14
❌ Failed: 1
Success Rate: 93.3%
```

## Customization

### Add Custom Test Questions

Edit `getPredefinedQuestions()` in `ai-chat-test-agent.js`:

```javascript
function getPredefinedQuestions() {
    return [
        'Your custom question here',
        'Another test question',
    ];
}
```

### Adjust Verification Logic

Modify `verifyResponse()` function to change how responses are validated.

### Change Test Timeout

Update `CONFIG.timeout` in the script.

## CI/CD Integration

### GitHub Actions Example

```yaml
name: AI Chat Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: |
          cd tests
          npm install
          npx playwright install chromium
      - name: Run tests
        env:
          OPENAI_API_KEY: ${{ secrets.OPENAI_API_KEY }}
          PLUGIN_URL: ${{ secrets.PLUGIN_URL }}
        run: |
          cd tests
          npm run test:headless
```

## Troubleshooting

### "Could not find chat input field"

- Check that the plugin page is accessible
- Verify the page has loaded completely
- Update selectors in the script if plugin UI changed

### "No response received within timeout"

- Increase timeout in CONFIG
- Check that OpenAI API key is valid
- Verify plugin is working manually first

### Tests failing

- Run tests with `headless: false` to see what's happening
- Check browser console for errors
- Verify WordPress admin credentials

## Cost Estimate

- **Playwright**: Free (open source)
- **OpenAI API**: ~$0.01-0.05 per test run (15 questions)
- **Total**: Very affordable for regular testing

## Next Steps

1. Run initial test suite
2. Review failed tests
3. Fix plugin issues
4. Re-run tests
5. Set up CI/CD for automated testing

