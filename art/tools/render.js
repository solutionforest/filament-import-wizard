// Renders art/logo.png, art/social-preview.png (1280×640, GitHub "Social preview") and art/og-image.png (1200×630)
// from art/logo.svg and a screenshot.   Usage: node art/tools/render.js   (needs: npm i playwright)
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
const art = path.resolve(__dirname, '..');
const svg = fs.readFileSync(path.join(art, 'logo.svg'), 'utf8');
const shot = 'data:image/png;base64,' + fs.readFileSync(path.join(art, 'screenshots', 'hero-review.png')).toString('base64');

const card = (w, h) => `<!doctype html><meta charset="utf-8"><style>
*{box-sizing:border-box;margin:0}
body{width:${w}px;height:${h}px;overflow:hidden;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#fff;
 background:radial-gradient(1200px 700px at 85% 10%,#4f46e5 0,transparent 60%),radial-gradient(900px 600px at 0% 100%,#7c3aed 0,transparent 55%),#1e1b4b;position:relative}
.left{position:absolute;left:${w * 0.055}px;top:0;bottom:0;width:${w * 0.46}px;display:flex;flex-direction:column;justify-content:center;gap:${h * 0.045}px}
.logo{width:${h * 0.2}px;height:${h * 0.2}px;filter:drop-shadow(0 12px 30px rgba(0,0,0,.35))}
h1{font-size:${h * 0.108}px;line-height:1.05;font-weight:800;letter-spacing:-.02em}
p{font-size:${h * 0.043}px;line-height:1.35;color:#e0e7ff;max-width:${w * 0.43}px}
.pills{display:flex;flex-wrap:wrap;gap:10px;margin-top:4px}
.pills span{font-size:${h * 0.03}px;font-weight:600;padding:${h * 0.011}px ${h * 0.024}px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22)}
.frame{position:absolute;right:${-w * 0.06}px;top:${h * 0.14}px;width:${w * 0.56}px;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 40px 90px rgba(0,0,0,.5);transform:rotate(-3deg)}
.bar{height:${h * 0.055}px;background:#f1f5f9;display:flex;align-items:center;gap:8px;padding-left:16px}
.bar i{width:${h * 0.02}px;height:${h * 0.02}px;border-radius:50%;background:#cbd5e1;display:block}
.frame img{display:block;width:100%}
</style><body><div class="left"><div class="logo">${svg}</div><h1>Filament<br>Import Wizard</h1>
<p>Import &amp; export CSV and Excel in Filament — map, review every row, then import. Files of any size.</p>
<div class="pills"><span>Upload → Map → Review → Import</span><span>XLSX · CSV</span><span>Export · API</span></div></div>
<div class="frame"><div class="bar"><i></i><i></i><i></i></div><img src="${shot}"></div></body>`;

(async () => {
  const browser = await chromium.launch();
  for (const [file, w, h] of [['social-preview.png', 1280, 640], ['og-image.png', 1200, 630]]) {
    const page = await browser.newPage({ viewport: { width: w, height: h } });
    await page.setContent(card(w, h)); await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(art, file) });
  }
  const page = await browser.newPage({ viewport: { width: 512, height: 512 } });
  await page.setContent(`<style>body{margin:0}svg{width:512px;height:512px;display:block}</style>${svg}`);
  await page.screenshot({ path: path.join(art, 'logo.png'), omitBackground: true });
  await browser.close();
})();
