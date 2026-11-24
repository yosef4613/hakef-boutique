// Checks the live site after going live: every page answers 200 and is indexable, old URLs redirect,
// recipes and sitemaps work, and nothing is broken. usage: node deploy/tools/verify_live.js [base]
const { chromium, devices } = require('playwright');
const BASE = (process.argv[2] || 'https://hakef.com').replace(/\/$/, '');
const REDIRECTS = [
  ['/product/קרם-נוגט/', '/shop/'], ['/product-category/שוקולד/', '/shop/'],
  ['/product-category/עוגות/עוגות-מוס/', '/product-category/עוגות/'], ['/מוס-שוקולד/', '/מתכונים/עוגת-אוכמניות-ופירורים/'],
  ['/עוגת-קפה-נוגט/', '/מתכונים/עוגת-קפה-נוגט/'], ['/cart/', '/contact/'], ['/hello-world/', '/'],
];
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ ...devices['iPhone 13'], deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  const bad = []; page.on('response', r => { if (r.status() >= 400 && r.url().startsWith(BASE)) bad.push(r.status() + ' ' + decodeURI(r.url())); });
  const seen = new Set(); const queue = [BASE + '/']; const rows = [];
  while (queue.length && seen.size < 80) {
    const u = queue.shift().split('#')[0]; if (seen.has(u)) continue; seen.add(u);
    const res = await page.goto(u, { waitUntil: 'load', timeout: 60000 });
    const info = await page.evaluate(() => ({
      noindex: !!document.querySelector('meta[name=robots][content*=noindex]'),
      h1: document.querySelectorAll('h1').length,
      broken: [...document.images].filter(i => i.complete && i.naturalWidth === 0).length,
      links: [...document.querySelectorAll('a[href]')].map(a => a.href),
    }));
    rows.push(`${res.status()} ${decodeURI(u.replace(BASE, ''))}${info.noindex ? ' NOINDEX!' : ''}${info.h1 !== 1 ? ' h1=' + info.h1 : ''}${info.broken ? ' broken-img=' + info.broken : ''}`);
    for (const l of info.links) if (l.startsWith(BASE + '/') && !l.includes('/wp-admin') && !seen.has(l.split('#')[0])) queue.push(l);
  }
  const req = ctx.request;
  const redir = [];
  for (const [from, to] of REDIRECTS) {
    const r = await req.get(BASE + encodeURI(from), { maxRedirects: 0 });
    const loc = decodeURI((r.headers()['location'] || '').replace(BASE, ''));
    redir.push(`${r.status() === 301 && loc === to ? 'OK ' : 'BAD'} ${from} -> ${r.status()} ${loc}`);
  }
  const extra = [];
  for (const u of ['/robots.txt', '/sitemap.xml', '/wp-sitemap.xml', '/wp-login.php', '/xmlrpc.php']) {
    const r = await req.get(BASE + u, { maxRedirects: 0 });
    extra.push(`${r.status()} ${u}`);
  }
  console.log(rows.join('\n'));
  console.log('--- redirects\n' + redir.join('\n'));
  console.log('--- other\n' + extra.join('\n'));
  console.log(`--- pages ${rows.length} | js errors ${errors.length} | 4xx/5xx requests ${bad.length}`, bad.slice(0, 8));
  await b.close();
})();
