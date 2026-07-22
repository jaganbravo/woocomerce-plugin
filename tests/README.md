# Test Agent for Store Compass

Playwright + OpenAI regression harness for Store Compass (`tests/ai-chat-test-agent.js`).

## Setup

1. Install dependencies:
```bash
npm install
```

2. Configure environment variables in `tests/.env`:
```env
OPENAI_API_KEY=your_api_key_here
PLUGIN_URL=http://localhost:8080/wp-admin/admin.php?page=store-compass-for-woocommerce
WP_ADMIN_USER=admin
WP_ADMIN_PASS=admin
```

## Core Commands

```bash
npm run test:nlp:phase0:headless          # Baseline regression set
npm run test:orders:revenue:headless      # Orders/revenue capability set
npm run test:phase7:headless              # Expansion/hardening set
```

Also available:
- `npm run test` / `npm run test:headless` (AI-generated question mode)
- `npm run test:static[:headless]`
- `npm run test:ambiguous[:headless]`
- `npm run test:dates[:headless]`

## Question File Formats

The harness accepts either format:

```json
["Question 1", "Question 2"]
```

or:

```json
{
  "questions": ["Question 1", "Question 2"],
  "generatedAt": "optional",
  "count": 2
}
```

Matching expected intents are auto-discovered from:
- `<questions-file>-expected-intents.json`, or
- `<name-without--questions>-expected-intents.json`.

## Result Semantics

- Functional pass/fail is separated from infrastructure failures.
- Summary fields:
  - `Total Questions Attempted`
  - `Evaluated Tests (functional)`
  - `Infra Failures (excluded)`
- Success rate is computed from evaluated functional tests only.
- A PDF report is generated in `tests/reports/`.

## Notes

- `DATAVIZ_AI_DEBUG_INTENT` must be enabled in the WP environment for intent contract checks.
- If Playwright browsers are missing, run:
```bash
npx playwright install chromium
```
