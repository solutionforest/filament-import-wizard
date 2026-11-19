# Headless API — import without a Filament screen

The wizard's pipeline (normalise → map → review → import) is available as a plain class, for **API endpoints, queued jobs, console commands and tests**. It uses the
same engine as the screens: streamed reading, Excel / CSV / encodings, automatic column mapping, the importer's own rules with the compiled fast path, file-level checks,
Filament's queued importer and the optional audit trail.

```php
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;

$import = HeadlessImport::for(ContactImporter::class)
    ->upload($request->file('file'))        // or ->file('imports/people.xlsx', disk: 's3') for a file that is already stored
    ->autoMap();

$review = $import->review();                // every row checked, nothing written

if ($review->isValid()) {
    $run = $import->user($request->user())->start();   // queued, one job per slice — returns Filament's Import record
}
```

## An API in three endpoints

```php
// POST /imports          → upload + review
public function store(Request $request): JsonResponse
{
    $headless = HeadlessImport::for(ContactImporter::class)->upload($request->file('file'))->autoMap();

    abort_if($headless->missingRequiredColumns() !== [], 422, 'Map: '.implode(', ', $headless->missingRequiredColumns()));

    $review = $headless->review();
    $path = /* remember $headless's stored path, e.g. in a pending-imports table */;

    return response()->json(['path' => $path, 'review' => $review->toArray()]);
}

// POST /imports/start    → import the reviewed file
public function start(Request $request): JsonResponse
{
    $import = HeadlessImport::for(ContactImporter::class)->file($request->input('path'))->autoMap()
        ->user($request->user())->start();                 // throws ImportBlockedException when rows have problems

    return response()->json(ImportProgress::of($import)->toArray(), 202);
}

// GET /imports/{id}      → progress
public function show(Import $import): JsonResponse
{
    return response()->json(ImportProgress::of($import)->toArray());
}
```

`ImportProgress::toArray()` gives `{id, status: running|finished, total_rows, processed_rows, successful_rows, failed_rows, percent}`.

## What you can do

| Method | |
| --- | --- |
| `file($path, $disk)` / `upload($file, $disk, $directory)` | use a stored file / store an uploaded one (name sanitised, type allow-listed) |
| `sheets()` / `sheet($index)` | workbook sheets, for letting a client choose |
| `headers()` / `autoMap()` / `map([...])` / `mapping()` | the file's columns and the mapping; `map()` validates column names |
| `missingRequiredColumns()` | labels of required importer columns that have no file column |
| `options([...])` | importer options (only the ones the importer declares are used) |
| `checks([new UniqueInFile(['email'])])` | file-level checks |
| `review(?Closure $progress)` | check every row → `ReviewResult` (totals, errors by column, first failing rows, preview) |
| `start(bool $allowInvalid = false)` | queue the import; **refuses** (`ImportBlockedException`, carrying the review) when rows have problems unless `$allowInvalid` |
| `user($user)` · `chunkSize()` · `maxErrors()` · `previewRows()` · `fastValidation(false)` · `audit()` | tuning; `audit()` records the run in the audit trail |

Outside any Filament panel the defaults (disk, directory) come from `ImportWizardPlugin`'s defaults or your explicit arguments. Approval (maker–checker) is a screen feature
and is not part of the headless API. See also [Large files](large-files.md) (`import-wizard:run` is the console flavour of the same thing) and [Export](export.md) for the other direction.
