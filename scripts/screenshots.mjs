// Captures real screenshots of the Apex Accounting admin panel for the docs site.
// Requires: php artisan serve on :8123, demo data seeded, login demo@apex.test/password.
// Run: node scripts/screenshots.mjs
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { mkdirSync } from 'fs';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8123';
const T = 1; // tenant id
const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT = join(__dirname, '..', 'docs', 'images');
mkdirSync(OUT, { recursive: true });

// Curated screen list: key => path under the tenant (or absolute for login).
const SHOTS = [
  { key: 'dashboard', url: `/admin/${T}` },
  { key: 'accounts', url: `/admin/${T}/accounts` },
  { key: 'invoices', url: `/admin/${T}/invoices` },
  { key: 'invoice-create', url: `/admin/${T}/invoices/create` },
  { key: 'customers', url: `/admin/${T}/customers` },
  { key: 'customer-payments', url: `/admin/${T}/customer-payments` },
  { key: 'bills', url: `/admin/${T}/bills` },
  { key: 'bill-create', url: `/admin/${T}/bills/create` },
  { key: 'vendors', url: `/admin/${T}/vendors` },
  { key: 'journal-entries', url: `/admin/${T}/journal-entries` },
  { key: 'items', url: `/admin/${T}/items` },
  { key: 'bank-statements', url: `/admin/${T}/bank-statement-lines` },
  { key: 'reconciliations', url: `/admin/${T}/reconciliations` },
  { key: 'assets', url: `/admin/${T}/assets` },
  { key: 'recurring', url: `/admin/${T}/recurring-templates` },
  { key: 'pos-z-readings', url: `/admin/${T}/pos-z-readings` },
  { key: 'tax-returns', url: `/admin/${T}/tax-returns` },
  { key: 'exchange-rates', url: `/admin/${T}/exchange-rates` },
  { key: 'trial-balance', url: `/admin/${T}/trial-balance` },
  { key: 'balance-sheet', url: `/admin/${T}/balance-sheet` },
  { key: 'profit-and-loss', url: `/admin/${T}/profit-and-loss` },
  { key: 'cash-flow', url: `/admin/${T}/cash-flow` },
  { key: 'general-ledger', url: `/admin/${T}/general-ledger` },
  { key: 'ar-aging', url: `/admin/${T}/ar-aging` },
  { key: 'vat-summary', url: `/admin/${T}/vat-summary` },
  { key: 'sales-book', url: `/admin/${T}/sales-book-page` },
  { key: 'team', url: `/admin/${T}/team` },
  { key: 'audit-logs', url: `/admin/${T}/audit-logs` },
];

async function settle(page) {
  try { await page.waitForLoadState('networkidle', { timeout: 12000 }); } catch {}
  await page.waitForTimeout(800);
}

const results = [];

const browser = await chromium.launch();
const ctx = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  deviceScaleFactor: 2,
  colorScheme: 'light',
});
const page = await ctx.newPage();

// 1) Login page (logged out) — capture, then sign in.
await page.goto(`${BASE}/admin/login`, { waitUntil: 'domcontentloaded' });
await settle(page);
await page.screenshot({ path: join(OUT, 'login.png') });
results.push('login');

await page.fill('input[type="email"]', 'demo@apex.test');
await page.fill('input[type="password"]', 'password');
await Promise.all([
  page.waitForURL(/\/admin\/\d+/, { timeout: 20000 }).catch(() => {}),
  page.click('button[type="submit"]'),
]);
await settle(page);

// 2) Each screen by direct URL.
for (const shot of SHOTS) {
  try {
    const resp = await page.goto(`${BASE}${shot.url}`, { waitUntil: 'domcontentloaded', timeout: 25000 });
    await settle(page);
    const status = resp ? resp.status() : 0;
    if (shot.key === 'dashboard') {
      // Hide Filament's promo widget for a clean marketing-grade hero shot.
      await page.addStyleTag({ content: `.fi-wi-filament-info, .fi-widget:has(a[href*="filament"]), section:has(> a[href*="filament"]){display:none !important}` });
      await page.waitForTimeout(400);
    }
    await page.screenshot({ path: join(OUT, `${shot.key}.png`) });
    results.push(`${shot.key} (${status})`);
    console.log(`OK  ${shot.key} -> ${shot.url} [${status}]`);
  } catch (e) {
    console.log(`ERR ${shot.key} -> ${shot.url} : ${e.message}`);
    results.push(`${shot.key} FAILED`);
  }
}

await browser.close();
console.log('\nCaptured:', results.length);
console.log(results.join('\n'));
