# Filament plugin directory kit

Everything to list the plugin on [filamentphp.com/plugins](https://filamentphp.com/plugins) (submit at <https://filamentphp.com/author>, GitHub login). Regenerate the images with
`node art/tools/render-directory.js` after UI changes.

> **Decide before submitting:** the directory links to a GitHub repository people can install from. This package is currently **private / proprietary / self-hosted only**.
> To list it you need a public repository (or the paid-plugin route) and a licence that allows it. Nothing here publishes anything.

## Images (PNG for upload, WebP for the web — same content)

The submission form is behind a login and does not publish exact pixel sizes; the directory shows one hero image per plugin (also used as its share image) and serves optimised WebP. So the kit has the common ratios:

| File | Size | Use |
| --- | --- | --- |
| `thumbnail-1600x900.png` / `.webp` | 1600×900 (16:9) | **Plugin thumbnail / hero** — first choice |
| `thumbnail-1200x630.png` / `.webp` | 1200×630 (1.91:1) | Share image (Open Graph / social) if the form asks for that ratio |
| `icon-512.png` | 512×512, transparent | Icon / avatar if requested (also `../logo.svg`) |
| `screenshots/01-upload` … `07-audit` (`.png` / `.webp`) | 1920×1080 (16:9) | Screenshots gallery, in this order |

Screenshot order and captions: 1 *Drop a CSV or Excel file* · 2 *Map columns with a live preview* · 3 *Every row checked before anything is written* · 4 *Open it from any list page* · 5 *Start → progress → done*
(notifications) · 6 *Export that imports straight back* · 7 *Audit trail and approval*.

## Copy-paste fields

- **Name:** Filament Import Wizard
- **Tagline:** Import & export CSV and Excel with a map → review → import wizard.
- **Short description:** A four-step Upload → Map → Review → Import wizard for Filament v5 — CSV, XLSX, XLS and ODS, live preview, every row validated before anything is written, files of any size, export that imports straight back, and a headless API.
- **Compatibility:** Filament 5.x (Livewire 4), Laravel 12 / 13, PHP 8.2–8.4.
- **Multilingual:** yes — English, 简体中文, 繁體中文 (TW / HK).
- **Dark mode:** dark-mode styles are included (not visually verified in every screen).
- **Suggested tags / categories:** Import & Export · Developer tool · Actions · Pages · Notifications · Localization.
- **Links:** repository `https://github.com/solutionforest/filament-import-wizard` · docs `…/tree/main/docs` · video `docs/video/tutorial.mp4` · changelog `CHANGELOG.md`.
- **Pricing:** free or paid — your call (the code is proprietary today).

### Long description (Markdown)

**Import and export for Filament, done properly.** Upload a CSV or Excel file, map the columns, see **every row checked before anything is written**, then import — in a full page or a slide-over from any list.
Export the same data back to Excel / CSV in the layout the importer reads, so files round-trip. It builds on Filament's own `Importer` classes: your existing importers work unchanged.

- Four-step wizard with live preview tables; page or slide-over action; optional start / done Filament notifications
- CSV, XLSX, XLS, ODS; multi-sheet, odd encodings, typed cells; works with files from Laravel Excel, Simple Excel and Fast Excel
- Every row validated with your importer's rules (verified fast path: 50,000 rows in under a second), file-level duplicate checks, one rules class for forms *and* imports
- Huge files: streamed, resumable chunked upload with no PHP size limit, console import, multi-server friendly
- Export that round-trips, blank import templates, CSV formula guard
- Headless API for endpoints, jobs and commands
- Optional audit trail and maker–checker approval
- PHPStan level 10, 113 Pest tests, security tests, fuzz and mutation tested, CI matrix
