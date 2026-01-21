# Test Agent for Dataviz AI WooCommerce Plugin

Automated testing using Playwright and OpenAI for generating and verifying test questions.

## Setup

1. Install dependencies:
```bash
npm install
```

2. Configure environment variables (create `.env` file):
```env
OPENAI_API_KEY=your_api_key_here
PLUGIN_URL=http://localhost:8080/wp-admin/admin.php?page=dataviz-ai-woocommerce
WP_ADMIN_USER=admin
WP_ADMIN_PASS=admin
```

## Test Commands

### 1. AI-Generated Questions (Default)
Generates new questions using AI and saves them for future use:
```bash
npm test                    # Shortcut (works without 'run')
# or
npm run test               # Full command
npm run test:headless      # Run in headless mode
```

**Behavior:**
- First run: Generates 30-50 questions using OpenAI API
- Saves questions to `saved-questions.json`
- Subsequent runs: Uses saved questions (unless file is deleted)

### 2. Static Testing (Predefined Questions)
Uses a fixed set of predefined questions for consistent testing:
```bash
npm run test:static        # Note: Must use 'npm run' for scripts with colons
npm run test:static:headless  # Run in headless mode
```

**Note:** Scripts with colons (`:`) require `npm run` prefix. Only `test`, `start`, `stop` can be run without `run`.

**Behavior:**
- Always uses the same predefined questions
- No AI generation required
- Consistent test results across runs

## File Structure

```
tests/
├── ai-chat-test-agent.js    # Main test script
├── package.json              # Dependencies and scripts
├── saved-questions.json      # Auto-generated (gitignored)
├── reports/                  # PDF test reports (gitignored)
└── README.md                 # This file
```

## How It Works

1. **Question Generation:**
   - AI mode: Uses OpenAI to generate diverse test questions
   - Static mode: Uses predefined questions from code

2. **Test Execution:**
   - Opens browser (Playwright)
   - Logs into WordPress admin
   - Navigates to plugin page
   - Sends each question to chat interface
   - Waits for AI response
   - Verifies response quality using AI

3. **Report Generation:**
   - Creates PDF report with all Q&A pairs
   - Shows pass/fail status
   - Includes charts detection
   - Saves to `reports/test-report-{timestamp}.pdf`

## Saved Questions

When using AI-generated questions, they are saved to `saved-questions.json`:
```json
{
  "questions": ["question1", "question2", ...],
  "generatedAt": "2025-01-12T10:30:00.000Z",
  "count": 45
}
```

To regenerate questions:
- Delete `saved-questions.json` and run `npm test`
- Or manually edit the file

## Test Results

- **Console Output:** Real-time test progress and results
- **PDF Report:** Detailed report in `reports/` directory
- **Summary:** Pass/fail counts and success rate

## Troubleshooting

**AI generation fails:**
- Check OpenAI API key in `.env`
- Falls back to predefined questions automatically

**Browser issues:**
- Ensure Docker WordPress is running on `http://localhost:8080`
- Check WordPress admin credentials in `.env`

**Questions not loading:**
- Check `saved-questions.json` exists and is valid JSON
- Delete file to regenerate
