# Filament Import Wizard

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-0.9.0-4f46e5?style=flat-square">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.2%20%E2%80%93%208.4-777bb4?style=flat-square&logo=php&logoColor=white">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-12%20%7C%2013-ff2d20?style=flat-square&logo=laravel&logoColor=white">
  <img alt="Filament" src="https://img.shields.io/badge/Filament-5.x-fdae4b?style=flat-square">
  <img alt="Livewire" src="https://img.shields.io/badge/Livewire-4.x-fb70a9?style=flat-square">
  <img alt="PHPStan" src="https://img.shields.io/badge/PHPStan-level%2010-4f5b93?style=flat-square">
  <img alt="Tests" src="https://img.shields.io/badge/tests-80%20passing-2ea44f?style=flat-square">
  <img alt="Fuzz and mutation tested" src="https://img.shields.io/badge/fast%20path-fuzz%20%2B%20mutation%20tested-2ea44f?style=flat-square">
  <img alt="Stress tested" src="https://img.shields.io/badge/stress-200k%20rows-2ea44f?style=flat-square">
  <img alt="Formats" src="https://img.shields.io/badge/files-CSV%20%C2%B7%20XLSX%20%C2%B7%20XLS%20%C2%B7%20ODS-0ea5e9?style=flat-square">
  <img alt="Languages" src="https://img.shields.io/badge/i18n-en%20%C2%B7%20zh__CN%20%C2%B7%20zh__TW%20%C2%B7%20zh__HK-0ea5e9?style=flat-square">
  <img alt="Horizon" src="https://img.shields.io/badge/Horizon-supported-0ea5e9?style=flat-square">
  <img alt="Octane" src="https://img.shields.io/badge/Octane-reviewed-0ea5e9?style=flat-square">
  <img alt="Multi-panel" src="https://img.shields.io/badge/panels-multi--panel-0ea5e9?style=flat-square">
  <img alt="Distribution" src="https://img.shields.io/badge/distribution-self--hosted%20only-64748b?style=flat-square">
  <img alt="License" src="https://img.shields.io/badge/license-proprietary-64748b?style=flat-square">
</p>

> Internal package — self-hosted distribution only (private repository, not on Packagist).

A four-step **Upload → Map → Review → Import** page for [Filament](https://filamentphp.com) v5.
Drop in a CSV or Excel file, check every row *before* anything is written, then import it — files of any size.
It sits on top of Filament's own `Importer` classes, so **your existing importers work unchanged**.

![Review step](docs/images/3-review.png)

## Video tutorial

[![Watch the tutorial](docs/video/tutorial-preview.gif)](docs/video/tutorial.mp4)

▶ **[Watch the tutorial (mp4, 80 s)](docs/video/tutorial.mp4)** — upload an Excel file, map columns with a live preview, review every row, submit for approval,
approve and read the audit trail; then a **50,000-row file** (chunked upload, reviewed in about two seconds) and the interface in **Hong Kong Traditional Chinese**.
Chapters and how it was recorded: [docs/video-tutorial.md](docs/video-tutorial.md).

## Supported versions

| Package | PHP | Laravel | Filament | Livewire | Status |
| --- | --- | --- | --- | --- | --- |
| **0.8.x** | 8.2 – 8.4 ¹ | **12** ✅ · **13** ✅ · 11.28+ ² | **5.x** (tested on 5.11) | 4.x (via Filament 5) | **Current** |
| 0.2 – 0.7 | 8.2 – 8.4 ¹ | 12 · 13 | 5.x | 4.x | Superseded — upgrade to 0.8 |
| — | — | — | 3.x · 4.x | — | Not supported (built on Filament 5's importer and schema APIs) |

✅ = the full test suite (Pest, 80 tests + fuzz, mutation and stress suites) passes on it · ¹ run on PHP 8.4; 8.2 and 8.3 are allowed by the constraints but were not run ·
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

Prefer a button on a list page? The same wizard opens in a **slide-over** with Filament notifications:

```php
protected function getHeaderActions(): array
{
    return [ImportWizardAction::make()->importer(ContactImporter::class)];
}
```

## Documentation

| | |
| --- | --- |
| [Installation](docs/installation.md) | Private repo, migrations, queue |
| [Usage](docs/usage.md) | The page, the plugin, several panels |
| [How it works](docs/how-it-works.md) | The four steps, mapping, preview |
| [Slide-over action](docs/slide-over.md) | Open the wizard from a button, with start / done notifications |
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
