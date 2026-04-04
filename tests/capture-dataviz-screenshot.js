/**
 * Capture screenshot of Dataviz AI plugin admin page
 * Run: node capture-dataviz-screenshot.js
 * Requires: PLUGIN_URL, WP_ADMIN_USER, WP_ADMIN_PASS in .env or environment
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const PLUGIN_URL = process.env.PLUGIN_URL || 'http://localhost:8080/wp-admin/admin.php?page=dataviz-ai-woocommerce';
const LOGIN_URL = process.env.LOGIN_URL || 'http://localhost:8080/wp-login.php';
const WP_USER = process.env.WP_ADMIN_USER || 'admin';
const WP_PASS = process.env.WP_ADMIN_PASS || 'admin';
const OUTPUT_DIR = path.join(__dirname, '..', 'docker', 'wordpress', 'wp-content', 'uploads');
const OUTPUT_FILE = path.join(OUTPUT_DIR, 'dataviz-ai-screenshot.png');

async function captureScreenshot() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  try {
    await page.goto(LOGIN_URL, { waitUntil: 'networkidle', timeout: 15000 });
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 10000 });

    await page.goto(PLUGIN_URL, { waitUntil: 'networkidle', timeout: 15000 });
    await page.waitForTimeout(2000);

    if (!fs.existsSync(OUTPUT_DIR)) {
      fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }
    await page.screenshot({ path: OUTPUT_FILE, fullPage: false });
    console.log('Screenshot saved to:', OUTPUT_FILE);
  } catch (err) {
    console.error('Error:', err.message);
    process.exit(1);
  } finally {
    await browser.close();
  }
}

captureScreenshot();
