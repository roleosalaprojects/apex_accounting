const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const out = process.argv[2];
  const pages = JSON.parse(fs.readFileSync(`${out}/pages.json`, 'utf8')).map((u) => '/' + u.replace('{tenant}', '1'));
  // record pages with real ids
  pages.push('/admin/1/invoices/487', '/admin/1/bills/287', '/admin/1/customers/4', '/admin/1/vendors/1', '/admin/1/items/3', '/admin/1/sales-orders/2', '/admin/1/assets/1', '/admin/1/journal-entries/2252', '/admin/1/debit-memos/1', '/admin/1/accounts/36', '/admin/1/bank-accounts/1', '/admin/1/reconciliations/1');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const problems = [];
  let current = '';
  page.on('pageerror', (e) => problems.push(`${current}: JS ${e.message.slice(0, 160)}`));
  page.on('response', (r) => { if (r.status() >= 500) problems.push(`${current}: HTTP ${r.status()} ${r.url().replace('http://localhost:8080', '')}`); });
  page.on('console', (m) => { if (m.type() === 'error' && !m.text().includes('favicon')) problems.push(`${current}: console ${m.text().slice(0, 160)}`); });
  await page.goto('http://localhost:8080/admin/login');
  await page.fill('input[type="email"]', 'owner@apex.test');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/admin\/\d+/, { timeout: 30000 });
  let visited = 0;
  for (const p of pages) {
    current = p;
    try {
      const resp = await page.goto('http://localhost:8080' + p, { timeout: 45000 });
      await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
      if (!resp || resp.status() >= 400) problems.push(`${p}: status ${resp ? resp.status() : 'none'}`);
      visited++;
    } catch (e) { problems.push(`${p}: ${e.message.slice(0, 120)}`); }
  }
  console.log(`visited ${visited} pages, ${problems.length} problems`);
  for (const pr of problems) console.log(' - ' + pr);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
