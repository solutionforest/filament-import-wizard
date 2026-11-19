# Performance & stress tests

The wizard is built to stay flat in memory however big the file is. A stress suite proves it, and you can run it yourself:

```bash
composer stress                                   # 50,000 rows
IMPORT_WIZARD_STRESS_ROWS=200000 composer stress  # any size
```

It generates a file with 1 % malformed e-mails and ~0.4 % duplicates, runs each stage, prints time and **peak-memory growth**,
asserts the exact counts (rows, invalid rows, imported rows, failed-row records) and fails if memory grows. It is excluded
from the normal `pest` run (`@group stress`).

## Why Review was slow, and the fix (it is PHP, not Redis or the database)

Profiled per row on one core: Laravel's validator costs ≈ 150 µs for three fields (≈ 40 µs per field, `email:rfc` ≈ +55 µs). The importer's remap/cast
is ≈ 2 µs and the duplicate check ≈ 4 µs (after batching). So the time was **PHP running Laravel's validator** — neither Redis nor a database can validate
a row; they can only store results, and the only storage in Review (the duplicate seen-set) was already cheap. The fix is to do less work in PHP:

1. **Compiled fast path *(on by default, since 0.8.0)*.** Common single-field rules — `required`, `nullable`, `string`, `integer`, `numeric`, `min`, `max`, `in`,
   `email` / `email:rfc`, and any plain `ValidationRule` object (such as `PhoneNumber`) — are checked natively in a few microseconds. The fast path answers
   only one question, *"does this field certainly pass?"*, and is built never to be more permissive than Laravel: an unusual value, an unknown rule or any failure
   hands that row to Laravel's real validation, which also produces the messages. Results and messages are therefore **identical** with or without it
   — verified against Laravel by ~1.6 million randomized comparisons, exhaustive rule × value tables, boundary tables and a 27-mutant mutation test, which also found and fixed three bugs in its first version; see [Testing](testing.md). Switch it off with `->fastValidation(false)`. Rules it can't reproduce (`regex`, `unique`/`exists`,
   closures, cross-field rules, `email:dns`…) stay with Laravel; an importer that overrides `validateData()` is left alone.
2. **Faster real import.** Filament validates each row again inside the importer. Add `use ValidatesQuickly;` to the importer (one line) to give that step the same
   fast path — same errors, same messages.
3. **Batched duplicate check** *(0.7.0)* — the seen-store is written once per slice (one SQLite transaction / one query per 1,000 keys).
4. **Parallel Review** *(opt-in, 0.7.0)* — `->parallelReview()` runs one queue job per slice; time ≈ time ÷ workers. With the fast path you rarely need it (see below).

```php
class ContactImporter extends Importer
{
    use \SolutionForest\FilamentImportWizard\Concerns\ValidatesQuickly;   // real import
}
// Review gets the fast path automatically.
```

## Results

Run on PHP 8.4, SQLite in memory, `sync` queue (jobs run inline), one CPU core of a container — i.e. a *pessimistic, single-thread*
setup. `peak growth` is how much PHP's peak memory rose during the stage.

| Stage | 50,000 rows | 200,000 rows | Peak memory growth |
| --- | --- | --- | --- |
| Count / slice the CSV | 0.10 s | 0.41 s | 0 MB |
| **Review** — every row through remap → cast → validate, plus `UniqueInFile` | **0.65 s** (was 12.4 s) | **2.9 s** (was 49 s) | 4.5 MB, flat |
| **Import** — queued slices of 500 rows, errors recorded (importer uses `ValidatesQuickly`) | **8.5 s** (was 18.8 s) | **35 s** (was 77 s) | 6.6 MB → 8.1 MB |
| XLSX → CSV conversion | 2.3 s | 9.1 s | ~0 MB |
| Chunked-upload join (2.5 MB / 10 MB file) | 0.02 s | 0.06 s | file held by the test itself |

Review went from ~4,000 to **~77,000 rows/s** (about 19×) and the import from ~2,600 to ~5,800 rows/s. What remains in the import is the real work: your
`resolveRecord()`, the model fill and the database writes.

What it shows:

- **Memory is flat.** Quadrupling the file changes the peak by about a megabyte: nothing is loaded whole; rows stream through generators and slices.
- **Time is linear** — about 77,000 rows/s for Review and about 5,800 rows/s for the full import in this setup; the import is dominated by the importer's own
  work and the database writes, not by the wizard.
- **Review is interactive for any size.** It validates 2,000 rows per request (~25 ms each here), and a request keeps slicing for up to a second, so a 50,000-row file is reviewed in one or two requests (about 1.9 s end to end in a browser in the tutorial video),
  never one long request.
- **Correctness holds under load** — exact invalid / duplicate / imported / failed counts at 50k and 200k rows.

## Reading the numbers for your app

- Import throughput is what your `resolveRecord()`, hooks and database do per row; a real queue with several workers processes
  *different* imports in parallel (slices of one import run one after another — see [Scaling](scaling.md)).
- MySQL / PostgreSQL over a network will be slower per write than in-memory SQLite; run `composer stress` against your own stack
  (point the Testbench database at it) to get your numbers.
- Tune `->chunkSize()` (rows per job), `->rowsPerReviewRequest()` and `IMPORT_WIZARD_JOB_TIMEOUT` from the measured rows/s.
- Not covered by the suite: multi-gigabyte files, a live Octane server, S3 latency. The design (byte-offset slices, streamed reads,
  chunked uploads) does not depend on file size, but verify on your infrastructure before promising a number.
