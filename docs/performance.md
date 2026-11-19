# Performance & stress tests

The wizard is built to stay flat in memory however big the file is. A stress suite proves it, and you can run it yourself:

```bash
composer stress                                   # 50,000 rows
IMPORT_WIZARD_STRESS_ROWS=200000 composer stress  # any size
```

It generates a file with 1 % malformed e-mails and ~0.4 % duplicates, runs each stage, prints time and **peak-memory growth**,
asserts the exact counts (rows, invalid rows, imported rows, failed-row records) and fails if memory grows. It is excluded
from the normal `pest` run (`@group stress`).

## Speeding up Review

Where the time goes (profiled, per row, single core): Laravel's validator costs ≈ 150 µs for three fields (≈ 40 µs per field, ≈ 55 µs more
for `email:rfc`); the importer's remap/cast is ≈ 2 µs; the duplicate check was ≈ 28 µs per row. So Review is bounded by Laravel's own validation —
and there are three levers:

1. **Batched duplicate check** *(on by default, since 0.6.1)* — the seen-store is now written once per slice, in one transaction (SQLite) or
   one query per 1,000 keys (database store) instead of one write per row: 28 µs → 4 µs per row, and on a networked database the saving is far larger.
   50,000 rows: **12.4 s → 9.0 s**.
2. **Parallel Review** *(opt-in)* — `->parallelReview()` reviews the file as a batch of queue jobs, one per slice, collected by the page.
   Slices are independent, so **time ≈ sequential time ÷ number of workers** (CPU-bound work). Requires a real queue and running workers; falls back to the
   polled pass on the `sync` driver, and when the page declares file-level `getRowChecks()` (those must see rows in order). A failed slice
   shows an error instead of a half result.
3. **Lighter rules** — the rules are yours: `email:rfc` costs ~55 µs/row more than a plain `string|max` check. Use the strictness you need.

```php
ImportWizardPlugin::make()->pages([ImportContacts::class])->parallelReview(queue: 'imports');
```

Measured here (**one CPU core**): a real run through the `database` queue with two `queue:work` processes reviewed 50,000 rows in 25 slices with the
exact same result (500 invalid) in ~9 s — as expected on a single core, where workers only compete. The speed-up appears with more cores / worker
machines; I could not measure it in this one-core environment, so treat ÷ workers as the expected scaling, not a benchmark, and measure on yours.

## Results

Run on PHP 8.4, SQLite in memory, `sync` queue (jobs run inline), one CPU core of a container — i.e. a *pessimistic, single-thread*
setup. `peak growth` is how much PHP's peak memory rose during the stage.

| Stage | 50,000 rows | 200,000 rows | Peak memory growth |
| --- | --- | --- | --- |
| Count / slice the CSV | 0.10 s | 0.42 s | 0 MB → 0 MB |
| **Review** — every row through remap → cast → validate, plus `UniqueInFile` | **9.0 s** (was 12.4 s) | 49.1 s (before batching; not re-run) | **5 MB**, flat |
| **Import** — 100 / 400 queued slices of 500 rows, errors recorded | 18.8 s | 77.0 s | **6.3 MB → 7.4 MB** |
| XLSX → CSV conversion | 2.2 s | 9.0 s | 0.4 MB → 0 MB |
| Chunked-upload join (2.5 MB / 10 MB file) | 0.02 s | 0.05 s | file held by the test itself |

What it shows:

- **Memory is flat.** Quadrupling the file changes the peak by about a megabyte: nothing is loaded whole; rows stream through generators and slices.
- **Time is linear** — about 5,500 rows/s for Review (4,000 before batching) and about 2,600 rows/s for the full import in this setup. Both are dominated by the importer's
  own rules and database writes, not by the wizard.
- **Review is interactive for any size.** It validates 2,000 rows per request (~0.35 s each here), so a 50,000-row file is about 25 short polls with a progress bar,
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
