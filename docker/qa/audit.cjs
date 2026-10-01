const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const S = process.argv[2];
  const shotDir = `${S}/vault-out/QA/screenshots/${new Date().toISOString().slice(0, 10)}`;
  fs.mkdirSync(shotDir, { recursive: true });
  const urls = JSON.parse(fs.readFileSync(`${S}/urls.json`, 'utf8'));
  const browser = await chromium.launch({ channel: 'chrome' });   // the installed Google Chrome
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const version = browser.version();
  const results = [];
  let current = null;
  const note = (kind, text) => { if (current) current.problems.push(`${kind}: ${text.slice(0, 200)}`); };
  page.on('pageerror', (e) => note('js', e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon/.test(m.text())) note('console', m.text()); });
  page.on('response', (r) => { if (r.status() >= 400 && !/favicon|livewire\/update/.test(r.url())) note('http', `${r.status()} ${r.url().replace('http://localhost:8080', '')}`); });
  const slug = (u) => u.replace(/^\/admin\/?/, '').replace(/\/$/, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'admin';
  // the login page first, signed out
  for (const u of ['/admin/login']) {
    current = { uri: u, problems: [] };
    const t = Date.now();
    await page.goto('http://localhost:8080' + u, { waitUntil: 'networkidle', timeout: 60000 });
    current.title = (await page.locator('h1, h2').first().textContent().catch(() => ''))?.trim() || await page.title();
    current.ms = Date.now() - t; current.status = 200;
    current.file = `${slug(u)}.png`;
    await page.screenshot({ path: `${shotDir}/${current.file}`, fullPage: true });
    results.push(current);
  }
  await page.fill('input[type="email"]', 'owner@apex.test');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/admin\/\d+/, { timeout: 30000 });
  await page.evaluate(() => localStorage.setItem('theme', 'light'));
  for (const { uri } of urls) {
    if (uri === '/admin/login') continue;
    current = { uri, problems: [] };
    const t = Date.now();
    let resp = null;
    try {
      resp = await page.goto('http://localhost:8080' + uri, { waitUntil: 'load', timeout: 60000 });
      await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => note('timing', 'network still busy after 20s'));
      await page.waitForTimeout(400);
    } catch (e) { note('nav', e.message); }
    current.status = resp ? resp.status() : 0;
    current.title = (await page.locator('h1').first().textContent().catch(() => ''))?.trim() || (await page.title());
    current.ms = Date.now() - t;
    current.file = `${slug(uri)}.png`;
    try { await page.screenshot({ path: `${shotDir}/${current.file}`, fullPage: true }); } catch (e) { note('shot', e.message); }
    results.push(current);
  }
  fs.writeFileSync(`${S}/audit-results.json`, JSON.stringify({ browser: 'Google Chrome ' + version, results }, null, 2));
  const bad = results.filter((r) => r.status !== 200 || r.problems.length);
  console.log(`${results.length} pages shot with Chrome ${version}; ${bad.length} with findings`);
  for (const r of bad) console.log(` - ${r.uri} [${r.status}] ${r.problems.join(' | ')}`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
