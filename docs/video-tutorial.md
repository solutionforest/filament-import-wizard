# Video tutorial

[![Tutorial preview](video/tutorial-preview.gif)](video/tutorial.mp4)

**[Watch the tutorial (mp4, 115 s)](video/tutorial.mp4)** — recorded against the bundled demo with `workbench/samples/contacts-small.csv`, `contacts.xlsx`
and a generated 50,000-row CSV.

## Chapters

| # | Chapter | What you see |
| --- | --- | --- |
| 0 | **Slide-over** | An **Import** button on the Contacts list opens the wizard in a slide-over; upload → map → review → import with Filament start / done notifications; the table is up to date afterwards |
| 1 | **Upload** | Drop a CSV or Excel file (full-page wizard from here) |
| 2 | **Map** | Columns matched automatically; sample values; a live preview table of how the first rows will be imported |
| 3 | **Review** | Every row checked with the importer's own rules before anything is written; bad cells highlighted, duplicates in the file caught |
| 4 | **Approval** *(optional)* | "Submit for approval" — a second person releases the import |
| 5 | **Import audit** *(optional)* | Who uploaded what, with the file's SHA-256, and its status |
| 6 | **Approve → imported** | 18 rows imported, 3 failed rows downloadable from Filament's notification |
| 7 | **History** | The immutable trail of every step |
| 8 | **A big file** | 50,000 rows sent in resumable chunks (no PHP upload limit), mapped automatically, **reviewed in about two seconds** with exact counts |
| 9 | **Languages** | The whole interface in Hong Kong Traditional Chinese (also English, 简体中文, 繁體中文 TW) |

## Follow along

```bash
composer install && composer serve      # http://127.0.0.1:8765/admin
```

Open **Import contacts** and upload `workbench/samples/contacts.xlsx` — see the [demo README](../workbench/README.md). Switch the demo's language with
`?lang=zh_HK` (also `zh_TW`, `zh_CN`, `en`).

## Re-recording it

The clip is recorded against the demo by `workbench/tools/record-tutorial.js` (Playwright, with on-screen captions) and converted with ffmpeg; the commands,
including how to generate the 50,000-row file, are in the script header. Re-record after UI changes so the video matches the product.
