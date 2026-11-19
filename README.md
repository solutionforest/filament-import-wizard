# Filament Import Wizard

> Internal package — self-hosted distribution only (private repository, not on Packagist).

A four-step **Upload → Map → Review → Import** page for [Filament](https://filamentphp.com) v5.
Drop in a CSV or Excel file, check every row *before* anything is written, then import it — files of any size.
It sits on top of Filament's own `Importer` classes, so **your existing importers work unchanged**.

![Review step](docs/images/3-review.png)

## Video tutorial

[![Watch the 1-minute tutorial](docs/video/tutorial-preview.gif)](docs/video/tutorial.mp4)

▶ **[Watch the tutorial (mp4, 57 s)](docs/video/tutorial.mp4)** — upload an Excel file, map columns with live preview, review
every row, submit for approval, approve, and read the audit trail. Chapters and how it was recorded: [docs/video-tutorial.md](docs/video-tutorial.md).

## Why use it

- **CSV, XLSX, XLS, ODS** — multi-sheet workbooks, odd encodings and delimiters handled for you.
- **Check first** — every row is validated with your importer's own rules, with a live preview table and the exact problems, before the import starts.
- **Smart column mapping** — columns are matched automatically; you only fix what's wrong.
- **Huge files** — streamed, never loaded into memory; resumable chunked upload bypasses PHP's size limit.
- **One rules class** for Filament forms *and* imports.
- **Optional audit trail and approval step** (maker–checker).
- **Translated** — English, 简体中文, 繁體中文 (TW / HK); add your own locale in minutes.
- **Stress-tested** — 50,000 and 200,000 rows with flat memory; Review of 50,000 rows in under a second ([results](docs/performance.md)).
- Multi-panel, multi-server, Horizon and Octane friendly. PHPStan level 10.

## Supported versions

| Package | PHP | Laravel | Filament | Livewire | Status |
| --- | --- | --- | --- | --- | --- |
| **0.8.x** | 8.2 – 8.4 ¹ | **12** ✅ · **13** ✅ · 11.28+ ² | **5.x** (tested on 5.11) | 4.x (via Filament 5) | **Current** |
| 0.2 – 0.7 | 8.2 – 8.4 ¹ | 12 · 13 | 5.x | 4.x | Superseded — upgrade to 0.8 |
| — | — | — | 3.x · 4.x | — | Not supported (built on Filament 5's importer and schema APIs) |

✅ = the full test suite (Pest, 75 tests + fuzz, mutation and stress suites) passes on it · ¹ run on PHP 8.4; 8.2 and 8.3 are allowed by the constraints but were not run ·
² allowed by Filament 5, not run — every Laravel 11.x release is currently flagged by Composer security advisories, so upgrade to 12+.

| Environment | Status |
| --- | --- |
| Queue | `sync` (tested), `database` / `redis` / `sqs` via Filament batches · **Horizon**: supported — see [Scaling](docs/scaling.md) |
| **Octane** | Code-reviewed for state leaks; not run under a live Octane server — see [Scaling](docs/scaling.md) |
| Database | SQLite (tested). MySQL / PostgreSQL use only standard Laravel query-builder calls but were not run here |
| File storage | `local` (tested). Any Flysystem disk (S3, NFS…) is supported by design; S3 was not run here |
| Browsers | Chromium (tested) |

Release notes: [CHANGELOG.md](CHANGELOG.md).

## Install

```bash
composer config repositories.filament-import-wizard vcs git@github.com:solutionforest/filament-import-wizard.git
composer require solution-forest/filament-import-wizard:^0.8
php artisan migrate
```

You also need Filament's import tables and a queue worker — see [Installation](docs/installation.md).

## Quick start

**1. Make a page for an importer you already have**

```php
use SolutionForest\FilamentImportWizard\Pages\ImportWizardPage;

class ImportContacts extends ImportWizardPage
{
    protected static ?string $importer = ContactImporter::class;
}
```

**2. Register it on a panel**

```php
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;

$panel->plugin(ImportWizardPlugin::make()->pages([ImportContacts::class]));
```

**3. Open the page** — upload, map, review, import. That's it.

## Documentation

| | |
| --- | --- |
| [Installation](docs/installation.md) | Private repo, migrations, queue |
| [Usage](docs/usage.md) | The page, the plugin, several panels |
| [How it works](docs/how-it-works.md) | The four steps, mapping, preview |
| [Rules](docs/rules.md) | One rules class for forms and imports; file-level checks |
| [File formats](docs/file-formats.md) | CSV, Excel, encodings, sheets |
| [Large files](docs/large-files.md) | Chunked upload, console import |
| [Audit & approval](docs/audit-and-approval.md) | Optional trail and maker–checker |
| [Scaling](docs/scaling.md) | Several servers, Horizon, Octane |
| [Configuration](docs/configuration.md) | Every option in one place |
| [Localization](docs/localization.md) | Languages, overriding texts, adding a locale |
| [Performance](docs/performance.md) | Stress test results (50k–200k rows) and how to run them |
| [Testing](docs/testing.md) | How the fast validation path is verified (fuzz, exhaustive, mutation) |
| [Video tutorial](docs/video-tutorial.md) | Chapters and how it was recorded |

## Try the demo

```bash
git clone git@github.com:solutionforest/filament-import-wizard.git && cd filament-import-wizard
composer install && composer serve      # http://127.0.0.1:8765/admin
```

Upload `workbench/samples/contacts.xlsx`. The [demo README](workbench/README.md) explains what to click and where each piece lives.

## Development

```bash
composer check     # PHPStan level 10 + Pest
composer stress    # 50,000-row stress test (flat memory, exact counts)
composer fuzz      # 300,000-case differential fuzz of the fast validation path vs Laravel
composer mutate    # mutation test of the fast path
```

Proprietary — see [LICENSE](LICENSE).
