# Filament Import Wizard

> Internal package — self-hosted distribution only (private repository, not on Packagist).

A four-step **Upload → Map → Review → Import** page for [Filament](https://filamentphp.com) v5.
Drop in a CSV or Excel file, check every row *before* anything is written, then import it — files of any size.
It sits on top of Filament's own `Importer` classes, so **your existing importers work unchanged**.

![Review step](docs/images/3-review.png)

## Why use it

- **CSV, XLSX, XLS, ODS** — multi-sheet workbooks, odd encodings and delimiters handled for you.
- **Check first** — every row is validated with your importer's own rules, with a live preview table and the exact problems, before the import starts.
- **Smart column mapping** — columns are matched automatically; you only fix what's wrong.
- **Huge files** — streamed, never loaded into memory; resumable chunked upload bypasses PHP's size limit.
- **One rules class** for Filament forms *and* imports.
- **Optional audit trail and approval step** (maker–checker).
- Multi-panel, multi-server, Horizon and Octane friendly. PHPStan level 10.

## Install

```bash
composer config repositories.filament-import-wizard vcs git@github.com:solutionforest/filament-import-wizard.git
composer require solution-forest/filament-import-wizard:^0.5
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

## Try the demo

```bash
git clone git@github.com:solutionforest/filament-import-wizard.git && cd filament-import-wizard
composer install && composer serve      # http://127.0.0.1:8765/admin
```

Upload `workbench/samples/contacts.xlsx`. The [demo README](workbench/README.md) explains what to click and where each piece lives.

## Development

```bash
composer check     # PHPStan level 10 + Pest
```

Proprietary — see [LICENSE](LICENSE).
