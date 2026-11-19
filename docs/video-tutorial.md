# Video tutorial

[![Tutorial preview](video/tutorial-preview.gif)](video/tutorial.mp4)

**[Watch the tutorial (mp4, 57 s)](video/tutorial.mp4)** — it runs the bundled demo with `workbench/samples/contacts.xlsx`.

## Chapters

1. **Upload** — drop a CSV or Excel file (large files travel in resumable chunks).
2. **Map** — columns are matched automatically; the preview table shows how the first rows will be imported.
3. **Review** — every row is checked with the importer's own rules before anything is written; bad cells are highlighted and
   duplicates in the file are caught.
4. **Submit for approval** *(optional)* — a second person releases the import.
5. **Import audit** *(optional)* — who uploaded what, with the file's SHA-256, and its status.
6. **Approve → imported** — 18 rows imported, 3 failed rows downloadable from Filament's notification.
7. **History** — the immutable trail of every step.

## Follow along

```bash
composer install && composer serve      # http://127.0.0.1:8765/admin
```

Open **Import contacts** and upload `workbench/samples/contacts.xlsx` — see the [demo README](../workbench/README.md).

## Re-recording it

The clip is recorded against the demo by `workbench/tools/record-tutorial.js` (Playwright, with on-screen captions) and
converted with ffmpeg; the command is in the script header. Re-record after UI changes so the video matches the product.
