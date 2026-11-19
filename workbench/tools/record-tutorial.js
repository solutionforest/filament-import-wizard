// Records docs/video/tutorial.mp4's source clip against the running demo (composer serve).
// Usage: npm i playwright && node workbench/tools/record-tutorial.js <output-dir> workbench/samples/contacts.xlsx
// Then: ffmpeg -i <output-dir>/*.webm -c:v libx264 -crf 27 -pix_fmt yuv420p -movflags +faststart -an docs/video/tutorial.mp4
const { chromium } = require('playwright');
const dir = process.argv[2], file = process.argv[3];
const base = 'http://127.0.0.1:8765/admin';
const wait = (p, ms) => p.waitForTimeout(ms);
async function cap(p, title, sub) {
  await p.evaluate(([t, s]) => {
    let el = document.getElementById('iw-cap');
    if (!el) {
      el = document.createElement('div'); el.id = 'iw-cap';
      el.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:99999;background:rgba(17,24,39,.92);color:#fff;padding:14px 26px;border-radius:14px;font:600 20px/1.35 Inter,system-ui,sans-serif;text-align:center;max-width:900px;box-shadow:0 10px 30px rgba(0,0,0,.35)';
      document.body.appendChild(el);
    }
    el.innerHTML = '<div>' + t + '</div>' + (s ? '<div style="font-weight:400;font-size:16px;opacity:.85;margin-top:4px">' + s + '</div>' : '');
  }, [title, sub]);
}
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1280, height: 720 }, recordVideo: { dir, size: { width: 1280, height: 720 } } });
  const p = await ctx.newPage();
  await p.goto(base + '/import-contacts');
  await p.waitForSelector('input[type=file]', { state: 'attached' }); await wait(p, 1200);
  await cap(p, 'Filament Import Wizard', 'Upload → Map → Review → Import (CSV &amp; Excel, any size)');
  await wait(p, 3500);
  await cap(p, '1 · Upload', 'Drop a CSV, XLSX, XLS or ODS file — big files are sent in resumable chunks');
  await wait(p, 2500);
  await p.setInputFiles('input[type=file]', file);
  await p.waitForSelector('text=Map columns', { timeout: 30000 }); await wait(p, 1500);
  await cap(p, '2 · Map', 'Columns are matched for you — sample values under each field');
  await wait(p, 3500);
  await p.mouse.wheel(0, 700); await wait(p, 800);
  await cap(p, 'Live preview', 'See exactly how the first rows will be imported');
  await wait(p, 4000);
  await p.mouse.wheel(0, 800); await wait(p, 600);
  await p.click('button:has-text("Continue")');
  await p.waitForSelector('text=All 21 rows checked', { timeout: 30000 }); await wait(p, 800);
  await cap(p, '3 · Review', 'Every row is checked with your importer\'s own rules — before anything is written');
  await wait(p, 4000);
  await p.mouse.wheel(0, 600); await wait(p, 500);
  await cap(p, 'Problems, row by row', 'Bad cells are highlighted; duplicates in the file are caught too');
  await wait(p, 4500);
  await p.click('button:has-text("Submit for approval")');
  await p.waitForSelector('text=Submitted for approval', { timeout: 30000 }); await wait(p, 800);
  await cap(p, '4 · Approval (optional)', 'A second person releases the import — nothing is written until then');
  await wait(p, 3500);
  await p.goto(base + '/import-audit');
  await p.waitForSelector('.fi-badge:has-text("Pending approval")', { timeout: 30000 }); await wait(p, 1000);
  await cap(p, 'Import audit (optional)', 'Who uploaded what — with the file\'s SHA-256 — and its status');
  await wait(p, 3500);
  await p.click('button:has-text("Approve"), a:has-text("Approve")'); await wait(p, 800);
  await p.click('button:has-text("Confirm")');
  await p.waitForSelector('.fi-badge:has-text("Completed with failures")', { timeout: 30000 }); await wait(p, 1500);
  await cap(p, 'Approved → imported', '18 rows imported, 3 failed rows available to download');
  await wait(p, 3000);
  await p.click('button:has-text("History"), a:has-text("History")');
  await p.waitForSelector('text=SHA-256', { timeout: 15000 }); await wait(p, 800);
  await cap(p, 'Full history', 'Every step is recorded in an immutable trail');
  await wait(p, 4500);
  await cap(p, 'solutionforest/filament-import-wizard', 'See the README and docs/ for installation and usage');
  await wait(p, 3500);
  await ctx.close(); await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
