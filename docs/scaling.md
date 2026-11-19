# Scaling

## Several web / queue servers

| Concern | What to do |
| --- | --- |
| Files | Use a **shared disk** (S3, NFS…) for `->disk()`. Uploads, converted CSVs and chunks must be readable by every web server *and* every queue worker. |
| Browser upload | Enable `->chunkedUploads()`; chunk state is on the disk, so a load balancer may send each chunk to a different server. (Livewire's own temporary upload would need `livewire.temporary_file_upload.disk` on shared storage.) |
| Review state | Set `IMPORT_WIZARD_SEEN_STORE=database` so `UniqueInFile` keeps its seen-set in the shared database; the default `sqlite` file is local to one server. |
| Queue | A real driver (`redis`, `database`, `sqs`); workers anywhere that can reach the same disk and database; the `job_batches` table; a shared cache (Redis) for Filament's job overlap locks. |
| Sessions | A shared session driver, as for any load-balanced app. |
| Audit | Lives in the database — central. |
| Housekeeping | Schedule `import-wizard:prune-uploads` on one server. |

## Horizon

With `->parallelReview(queue: 'imports')` the Review step also runs on your workers (jobs `ReviewSlice`, same timeout setting), so give that queue enough
workers — see [Performance](performance.md).

Slices are ordinary queued jobs (`ImportCsvSlice`), tagged by Filament (`import{id}`) so they show up in Horizon.

- **Route them** with your importer: `getJobQueue()`, `getJobConnection()`, `getJobBatchName()` — e.g. a dedicated `imports` queue and supervisor.
- **Timeouts.** Each slice sets `$timeout` from `import-wizard.job_timeout` (default 600 s, env `IMPORT_WIZARD_JOB_TIMEOUT`). The Horizon
  supervisor's `timeout` must be **at least that**, and the connection's `retry_after` must be **greater** than the supervisor timeout.
  If a slice of your importer is slow, lower `->chunkSize()` rather than raising the timeout.
- **Overlap.** Filament's `getJobMiddleware()` (`WithoutOverlapping` per import) runs the slices of *one* import one after another, so
  extra workers don't speed up a single import — they help many imports at once. If your importer is safe to run in parallel,
  override `getJobMiddleware()` to return `[]`.
- **Batches.** Horizon doesn't prune `job_batches`; schedule `queue:prune-batches`.
- **Memory.** A slice holds `chunkSize()` rows; the default 500 is small. Horizon's `memory` limit and worker recycling apply as usual.

```php
// config/horizon.php
'defaults' => ['imports' => ['connection' => 'redis', 'queue' => ['imports'], 'timeout' => 660, 'tries' => 1]],
```

## Octane

The package keeps **no mutable static state, no singletons and nothing request-scoped in a long-lived object**, which is what
breaks apps under Octane:

- the plugin instance is created once at boot and only holds settings; everything request-specific (current panel, user, IP,
  disk, directory) is read at call time;
- services (`AuditRecorder`, `ImportApprovals`, `RowValidator`, `CsvSource`, `ChunkedUpload`, rule classes) are created per use;
- row checks and seen-stores hold their state in per-instance objects backed by SQLite / the database, never in statics;
- the event listener is registered once per worker boot;
- files are streamed and temporary files are removed in `finally` blocks, so workers don't accumulate memory or disk.

Things to watch in **your** code:

- Closures you give the plugin (`requireApproval`, `canApproveUsing`, `canViewAllAuditsUsing`) live for the whole worker lifetime — don't capture the
  request, a user or a model in them; take them as parameters (they receive the user / audit).
- Joining the chunks of a very large upload runs inside one request: raise Octane's `max_execution_time` (Swoole) / the
  proxy timeout for `…/import-wizard/upload/complete`.

> Verified by code review and by running many wizard "requests" in one PHP process across several panels (see `tests/`). It was
> **not** exercised under a live Octane server (Swoole / RoadRunner / FrankenPHP) in the build environment — do a smoke test there.
