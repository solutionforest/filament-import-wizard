// Renders the Filament plugin-directory kit into art/filament-directory/:
//   thumbnail-1600x900.png (16:9), thumbnail-1200x630.png (share ratio), icon-512.png, screenshots/01..07-*.png (1920x1080)
// Usage: node art/tools/render-directory.js   (needs: npm i playwright)
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
const art = path.resolve(__dirname, '..');
const out = path.join(art, 'filament-directory');
fs.mkdirSync(path.join(out, 'screenshots'), { recursive: true });
const svg = fs.readFileSync(path.join(art, 'logo.svg'), 'utf8');
const b64 = f => 'data:image/png;base64,' + fs.readFileSync(path.join(art, 'screenshots', f)).toString('base64');

const base = (w, h, inner) => `<!doctype html><meta charset="utf-8"><style>
*{box-sizing:border-box;margin:0}body{width:${w}px;height:${h}px;overflow:hidden;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#fff;position:relative;
background:radial-gradient(${w * .9}px ${h * .8}px at 90% 0,#4f46e5 0,transparent 60%),radial-gradient(${w * .7}px ${h * .7}px at 0 100%,#7c3aed 0,transparent 55%),#1e1b4b}
.frame{position:absolute;border-radius:${h * .02}px;overflow:hidden;background:#fff;box-shadow:0 ${h * .04}px ${h * .1}px rgba(0,0,0,.5)}
.bar{height:${h * .05}px;background:#f1f5f9;display:flex;align-items:center;gap:8px;padding-left:${h * .02}px}
.bar i{width:${h * .018}px;height:${h * .018}px;border-radius:50%;background:#cbd5e1;display:block}
.frame img{display:block;width:100%}
</style><body>${inner}</body>`;

const thumb = (w, h) => base(w, h, `
<div style="position:absolute;left:${w * .055}px;top:0;bottom:0;width:${w * .44}px;display:flex;flex-direction:column;justify-content:center;gap:${h * .045}px">
 <div style="width:${h * .2}px;height:${h * .2}px;filter:drop-shadow(0 12px 30px rgba(0,0,0,.35))">${svg}</div>
 <div style="font-size:${h * .1}px;line-height:1.05;font-weight:800;letter-spacing:-.02em">Import<br>Wizard</div>
 <div style="font-size:${h * .045}px;line-height:1.35;color:#e0e7ff;max-width:${w * .42}px">Import &amp; export CSV and Excel. Map, review every row, then import.</div>
</div>
<div class="frame" style="right:${-w * .05}px;top:${h * .14}px;width:${w * .58}px;transform:rotate(-3deg)"><div class="bar"><i></i><i></i><i></i></div><img src="${b64('hero-review.png')}"></div>`);

const slide = (title, sub, file) => base(1920, 1080, `
<div style="position:absolute;left:0;right:0;top:56px;text-align:center"><div style="font-size:58px;font-weight:800;letter-spacing:-.02em">${title}</div><div style="font-size:30px;color:#c7d2fe;margin-top:10px">${sub}</div></div>
<div class="frame" style="left:50%;top:210px;width:1180px;transform:translateX(-50%)"><div class="bar"><i></i><i></i><i></i></div><img src="${b64(file)}"></div>`);

const slides = [
  ['01-upload', 'Drop a CSV or Excel file', 'XLSX, XLS, ODS, CSV — multi-sheet, any encoding, any size', '1-upload.png'],
  ['02-map', 'Map columns with a live preview', 'Matched automatically; see how the first rows will import', '2-map.png'],
  ['03-review', 'Every row checked before anything is written', 'Highlighted problems, exact messages, error counts per column', '3-review.png'],
  ['04-slide-over', 'Open it from any list page', 'A slide-over action, like Filament’s own import', '10-slideover-review.png'],
  ['05-notifications', 'Start → progress → done', 'Optional Filament notifications with a failed-rows download', '11-slideover-done.png'],
  ['06-export', 'Export that imports straight back', 'Excel / CSV in the importer’s layout, plus a blank template', '14-export-modal.png'],
  ['07-audit', 'Audit trail and approval', 'Who imported what, with a maker–checker approval step', '5-audit-pending.png'],
];

(async () => {
  const browser = await chromium.launch();
  const shoot = async (html, w, h, file, transparent = false) => {
    const page = await browser.newPage({ viewport: { width: w, height: h } });
    await page.setContent(html); await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(out, file), omitBackground: transparent });
    await page.close();
  };
  await shoot(thumb(1600, 900), 1600, 900, 'thumbnail-1600x900.png');
  await shoot(thumb(1200, 630), 1200, 630, 'thumbnail-1200x630.png');
  await shoot(`<style>body{margin:0}svg{width:512px;height:512px;display:block}</style>${svg}`, 512, 512, 'icon-512.png', true);
  for (const [name, title, sub, file] of slides) await shoot(slide(title, sub, file), 1920, 1080, `screenshots/${name}.png`);
  await browser.close();
})();
