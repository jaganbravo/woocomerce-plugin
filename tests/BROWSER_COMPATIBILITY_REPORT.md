# Browser compatibility smoke report

**Generated:** 2026-04-21 (UTC `2026-04-21T22:56:50.398Z`)  
**Environment:** macOS (`darwin`)  
**Base URL:** `http://127.0.0.1:8080/` (local Docker WordPress)

## Methodology

Automated checks used **Playwright** with three engines:

| Engine | Typical consumer browsers |
|--------|---------------------------|
| Chromium | Google Chrome, Microsoft Edge (Chromium) |
| Firefox | Mozilla Firefox |
| WebKit | Apple Safari (same rendering engine family) |

This is a **smoke test** only: each engine opened the URL, waited for `domcontentloaded`, and recorded HTTP status, final URL, document title, and approximate load time. It does **not** replace manual visual QA, Lighthouse scores, accessibility audits, or logged-in Dataviz AI UI flows.

## Summary

| Metric | Value |
|--------|--------|
| Total checks | 9 (3 pages × 3 engines) |
| Passed | 9 |
| Failed | 0 |

## Results matrix

### Home (`/`)

| Engine | HTTP | Title | Load (approx.) |
|--------|------|--------|----------------|
| Chromium | 200 | `dataviz` | ~3.0 s |
| Firefox | 200 | `dataviz` | ~3.0 s |
| WebKit | 200 | `dataviz` | ~9.5 s |

### Login (`/wp-login.php`)

| Engine | HTTP | Title | Load (approx.) |
|--------|------|--------|----------------|
| Chromium | 200 | `Log In ‹ dataviz — WordPress` | ~0.27 s |
| Firefox | 200 | `Log In ‹ dataviz — WordPress` | ~1.2 s |
| WebKit | 200 | `Log In ‹ dataviz — WordPress` | ~0.64 s |

### Dataviz admin (unauthenticated)

**URL:** `http://127.0.0.1:8080/wp-admin/admin.php?page=dataviz-ai-woocommerce`

| Engine | HTTP | Final URL | Title |
|--------|------|-----------|--------|
| Chromium | 200 | Redirect to `http://localhost:8080/wp-login.php?redirect_to=...` | `Log In ‹ dataviz — WordPress` |
| Firefox | 200 | Same redirect pattern | `Log In ‹ dataviz — WordPress` |
| WebKit | 200 | Same redirect pattern | `Log In ‹ dataviz — WordPress` |

**Observation:** WordPress redirected from `127.0.0.1` to **`localhost`** in the login URL. That is expected; keep hostnames consistent in bookmarks and automated tests to avoid cookie or redirect surprises.

## Out of scope (not covered by this report)

- Logged-in **Dataviz AI** admin screen, onboarding overlay, chat, or AJAX
- Performance (Lighthouse), SEO, or accessibility scores
- Real **Edge** or **Chrome** branded binaries (Chromium-class automation only)
- Console error collection (field was reserved; not populated in the run)

## Re-run locally

From the repo root:

```bash
cd tests
npx playwright install chromium firefox webkit   # once per machine
```

Then run the same matrix (example inline script used for this report):

```bash
node -e "
const { chromium, firefox, webkit } = require('playwright');
const targets = [
  { name: 'Home', url: 'http://127.0.0.1:8080/' },
  { name: 'Login page', url: 'http://127.0.0.1:8080/wp-login.php' },
  { name: 'Dataviz admin (unauthenticated)', url: 'http://127.0.0.1:8080/wp-admin/admin.php?page=dataviz-ai-woocommerce' },
];
const browsers = [['Chromium', chromium], ['Firefox', firefox], ['WebKit', webkit]];
(async () => {
  for (const [bName, launcher] of browsers) {
    for (const t of targets) {
      const browser = await launcher.launch({ headless: true });
      const page = await browser.newPage();
      const res = await page.goto(t.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      console.log(JSON.stringify({ browser: bName, page: t.name, status: res && res.status(), title: await page.title(), url: page.url() }));
      await browser.close();
    }
  }
})();
"
```

Point `PLUGIN_URL` / URLs at your staging host when Docker is not running.

## Related project tests

The AI chat agent (`npm test` in `tests/`) uses Playwright **Chromium** against `PLUGIN_URL` (default `http://localhost:8080/wp-admin/admin.php?page=dataviz-ai-woocommerce`). See `tests/ai-chat-test-agent.js` and `tests/package.json`.
