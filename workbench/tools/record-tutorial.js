// Records the tutorial clip against the running demo (composer serve), then convert with ffmpeg:
//   npm i playwright
//   python3 -c "print('Full Name,Work Email,Mobile,Position,Company'); [print(f'Person {i},' + (f'bad-{i}' if i%100==0 else f'p{i}@example.com') + f',+1 415 555 {i%10000:04d},Engineer,Acme') for i in range(1,50001)]" > /tmp/big-contacts.csv
//   node workbench/tools/record-tutorial.js <output-dir> workbench/samples/contacts.xlsx /tmp/big-contacts.csv
//   ffmpeg -i <output-dir>/*.webm -c:v libx264 -crf 27 -pix_fmt yuv420p -movflags +faststart -an docs/video/tutorial.mp4
const { chromium } = require('playwright');
const [dir, file, bigFile] = process.argv.slice(2);
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
async function openUpload(p, query = '') {
  await p.goto(base + '/import-contacts' + query);
  await p.waitForSelector('input[type=file]', { state: 'attached' }); await wait(p, 1200);
}
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1280, height: 720 }, recordVideo: { dir, size: { width: 1280, height: 720 } } });
  const p = await ctx.newPage();

  // ---- Intro + the 4 steps with an Excel file
  await openUpload(p, '?lang=en');
  await cap(p, 'Filament Import Wizard', 'Upload → Map → Review → Import · CSV &amp; Excel · any size');
  await wait(p, 3500);
  await cap(p, '1 · Upload', 'Drop a CSV, XLSX, XLS or ODS file');
  await wait(p, 2000);
  await p.setInputFiles('input[type=file]', file);
  await p.waitForSelector('text=Map columns', { timeout: 30000 }); await wait(p, 1500);
  await cap(p, '2 · Map', 'Columns are matched for you — sample values under each field');
  await wait(p, 3000);
  await p.mouse.wheel(0, 700); await wait(p, 800);
  await cap(p, 'Live preview', 'See exactly how the first rows will be imported');
  await wait(p, 3500);
  await p.mouse.wheel(0, 800); await wait(p, 600);
  await p.click('button:has-text("Continue")');
  await p.waitForSelector('text=All 21 rows checked', { timeout: 30000 }); await wait(p, 800);
  await cap(p, '3 · Review', 'Every row is checked with your importer\'s own rules — before anything is written');
  await wait(p, 3500);
  await p.mouse.wheel(0, 600); await wait(p, 500);
  await cap(p, 'Problems, row by row', 'Bad cells are highlighted; duplicates in the file are caught too');
  await wait(p, 4000);

  // ---- Approval + audit (optional features)
  await p.click('button:has-text("Submit for approval")');
  await p.waitForSelector('text=Submitted for approval', { timeout: 30000 }); await wait(p, 800);
  await cap(p, '4 · Approval (optional)', 'A second person releases the import — nothing is written until then');
  await wait(p, 3000);
  await p.goto(base + '/import-audit');
  await p.waitForSelector('.fi-badge:has-text("Pending approval")', { timeout: 30000 }); await wait(p, 1000);
  await cap(p, 'Import audit (optional)', 'Who uploaded what — with the file\'s SHA-256 — and its status');
  await wait(p, 3000);
  await p.click('button:has-text("Approve"), a:has-text("Approve")'); await wait(p, 800);
  await p.click('button:has-text("Confirm")');
  await p.waitForSelector('.fi-badge:has-text("Completed with failures")', { timeout: 30000 }); await wait(p, 1500);
  await cap(p, 'Approved → imported', '18 rows imported, 3 failed rows available to download');
  await wait(p, 2500);
  await p.click('button:has-text("History"), a:has-text("History")');
  await p.waitForSelector('text=SHA-256', { timeout: 15000 }); await wait(p, 800);
  await cap(p, 'Full history', 'Every step is recorded in an immutable trail');
  await wait(p, 3500);

  // ---- Big file: chunked upload + instant review
  await openUpload(p, '?lang=en');
  await cap(p, 'Now a big file: 50,000 rows', 'Sent in resumable chunks — no PHP upload limit');
  await wait(p, 2500);
  await p.setInputFiles('input[type=file]', bigFile);
  await p.waitForSelector('text=Map columns', { timeout: 60000 }); await wait(p, 1500);
  await cap(p, 'Mapped automatically', 'Same wizard, same preview — whatever the size');
  await wait(p, 2500);
  const started = Date.now();
  await p.click('button:has-text("Continue")');
  await p.waitForSelector('text=All 50,000 rows checked', { timeout: 60000 });
  const seconds = ((Date.now() - started) / 1000).toFixed(1);
  await cap(p, 'Review: 50,000 rows in ' + seconds + ' s', '500 bad rows found — flat memory, exact counts');
  await wait(p, 5000);

  // ---- Language
  await openUpload(p, '?lang=zh_HK');
  await cap(p, 'Translated', 'English · 简体中文 · 繁體中文 (TW / HK) — add your own language');
  await wait(p, 2500);
  await p.setInputFiles('input[type=file]', file);
  await p.waitForSelector('text=對應欄位', { timeout: 30000 }); await wait(p, 1500);
  await p.click('button:has-text("繼續")');
  await p.waitForSelector('text=已檢查全部 21 行', { timeout: 30000 }); await wait(p, 800);
  await cap(p, '香港繁體 · Hong Kong Traditional Chinese', 'Wizard, audit page, statuses and messages are all translated');
  await wait(p, 4500);
  await p.goto(base + '/import-contacts?lang=en'); await wait(p, 800);
  await cap(p, 'solutionforest/filament-import-wizard', 'See the README and docs/ for installation and usage');
  await wait(p, 3500);
  await ctx.close(); await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });
