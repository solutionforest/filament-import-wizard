# Large files

## Chunked uploads (no PHP size limit)

Livewire's normal upload is capped by `upload_max_filesize` / `post_max_size`. Turn on chunked uploads and the browser
slices the file and sends each slice (sized to fit your PHP limits) straight to the panel's disk:

```php
ImportWizardPlugin::make()
    ->pages([ImportContacts::class])
    ->chunkedUploads()
    ->maxUploadSize(20 * 1024 * 1024);   // KB — 20 GB
```

- Resumable per chunk (3 retries), progress bar, drag & drop.
- All state — a manifest and one object per chunk — lives **on the disk**, not in the session, so with a shared disk any
  web server can take any chunk.
- Endpoints are authenticated panel routes; uploads belong to the user who started them; only csv/txt/xlsx/xlsm/ods/xls
  are accepted; the size cap is enforced when the upload starts.
- Joining the chunks needs temporary local space equal to the file size and a request that can run long enough to copy it
  (see [Scaling](scaling.md) for Octane / PHP-FPM timeouts).
- Clean up abandoned uploads: `php artisan import-wizard:prune-uploads` (schedule it daily with `->onOneServer()`).

## Console import

For files already on a disk (scp, rsync, a mounted volume), run the same mapping, validation and queued import server-side:

```bash
php artisan import-wizard:run "App\Filament\Imports\ContactImporter" imports/big.csv --disk=s3 --user=1
php artisan import-wizard:run "App\Filament\Imports\ContactImporter" imports/big.xlsx --sheet=1 --map="email=Work Email" --dry-run
```

Columns are auto-mapped (override with `--map=column=Header`), every row is validated with progress, the first failing rows
are printed, and nothing is imported when there are problems unless `--force`. `--dry-run` only validates.

## How big can it go?

Memory stays flat: the file is read by byte offset, validated a few thousand rows per request, and imported by one queued
job per slice whose payload is just an offset and a row count. The limits are your disk, your queue throughput and
`max_execution_time` for the chunk-joining request. A 16 MB / 260,000-row CSV went through a stock `php -S` (2 MB / 8 MB
limits) in ten chunks in under a second.
