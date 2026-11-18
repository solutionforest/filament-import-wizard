# Filament Import Wizard

A step-by-step **Upload → Map → Review → Import** page for [Filament](https://filamentphp.com) v5 that
accepts **CSV and Excel (XLSX / ODS)** files, checks every row *before* anything is written, and imports
files of any size through Filament's own queued importer.

It is built on Filament's `Importer` / `ImportColumn` classes — **any importer you already have works unchanged.**

| 1 · Upload | 2 · Map |
| --- | --- |
| ![Upload](docs/1-upload.png) | ![Map](docs/2-map.png) |
| **3 · Review** | **4 · Import** |
| ![Review](docs/3-review.png) | ![Import](docs/4-import.png) |

## Features

- **CSV, XLSX and ODS** – drop a spreadsheet and the first sheet is streamed into a CSV (OpenSpout, already a Filament dependency); headers are taken from the first row.
- **Built for huge files** – the file is never loaded into memory. It is addressed by byte offset, validated in polled slices, and imported by one queued job per slice (`ImportCsvSlice`, a thin subclass of Filament's `ImportCsv`). The queue payload is just an offset and a row count.
- **Cross-check before the import starts** – the Review step dry-runs the importer's own *remap → cast → validate* pipeline over **every** row (nothing is saved) and shows live progress, valid / invalid counts and the first 50 failing rows with Filament's own validation messages. The Import button unlocks only when the check has finished.
- **Smart column mapping** – columns are matched by name, label, `guess()` aliases, normalised names (`E-mail` ↔ `email`), trailing words (`Full name` ↔ `name`) and fuzzy similarity. A file column is never used twice, sample values are shown under each field and required mappings are enforced.
- **Filament-native** – `FileUpload`, `ImportColumn::getSelect()`, schema components, `ImportDispatcher` (events, `imports` / `failed_import_rows` tables, failed-rows CSV download, completion notification) and Filament's CSS variables, so it follows your panel theme and dark mode.
- **Safe by default** – the uploaded path and all wizard state are Livewire `#[Locked]`; the file path must live inside the upload directory; mappings are checked against the real importer columns and file headers.

## Installation

```bash
composer require solution-forest/filament-import-wizard
```

Run Filament's import migrations (once) and make sure a queue worker is running in production:

```bash
php artisan make:queue-batches-table   # job_batches, if you do not have it yet
php artisan vendor:publish --tag=filament-actions-migrations
php artisan migrate
```

## Usage

Create a normal Filament importer (`php artisan make:filament-importer Contact`), then add a page for it:

```php
use SolutionForest\FilamentImportWizard\Pages\ImportWizardPage;

class ImportContacts extends ImportWizardPage
{
    protected static ?string $importer = ContactImporter::class;
}
```

Register it in your panel (`->pages([ImportContacts::class])`) and you are done. The page title, navigation and
authorization (`canAccess()`, `shouldRegisterNavigation()`) work like any Filament page.

### Tuning

Override these on your page class:

| Method | Default | Purpose |
| --- | --- | --- |
| `getUploadDisk()` | `filament.default_filesystem_disk` | Disk the upload is stored on |
| `getUploadDirectory()` | `import-wizard` | Directory the upload must live in |
| `getChunkSize()` | `500` | Rows per queued job |
| `getRowsPerReviewRequest()` | `2000` | Rows validated per poll in the Review step |
| `getMaxCollectedErrors()` | `50` | Failing rows shown in Review |
| `getMaxUploadSizeInKilobytes()` | `null` | Upload size cap |

## Try the demo

The repository ships a ready-to-run demo panel (Orchestra Workbench + SQLite, no setup):

```bash
git clone git@github.com:solutionforest/filament-import-wizard.git
cd filament-import-wizard
composer install
composer serve          # http://127.0.0.1:8765/admin  (auto-signed-in demo user)
```

Open **Import contacts** and upload `workbench/samples/contacts.xlsx` (or `contacts.csv`). The sample deliberately
contains 3 bad rows (two malformed e-mails, one missing name) so you can see Review catch them: 17 of 20 rows import,
3 are reported and downloadable as a failed-rows CSV. The demo importer lives in `workbench/app/Filament/Imports`.

> The demo signs a user in automatically (`workbench/app/Http/DemoLogin.php`) — demo only, never copy that.

## Limitations

- Excel: first sheet only, `.xlsx` / `.xlsm` / `.ods` (legacy `.xls` is not supported — save as XLSX). Cell values are read as displayed text/values; dates become `Y-m-d H:i:s`.
- CSV is read as UTF-8 (BOM handled); delimiter is auto-detected (`, ; | tab`).
- The upload goes through Livewire's temporary upload, so the maximum size is bounded by your PHP / Livewire limits (`upload_max_filesize`, `post_max_size`, `livewire.temporary_file_upload`). For multi-GB files raise them, or upload to the disk by other means and point the page at it.
- Review validates rows against the importer's rules only; `resolveRecord()`, hooks and database writes run in the real import. Rows are not de-duplicated against each other.
- Filament imports do not run per-record policy checks – authorize in your importer if you need to.

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT
