# Configuration

## Plugin (per panel)

| Method | Default | |
| --- | --- | --- |
| `pages([...])` | `[]` | Wizard pages to register |
| `disk(?string)` | `filament.default_filesystem_disk` | Where uploads are stored |
| `directory(string)` | `import-wizard` | Uploads go to `{directory}/{panel-id}/` |
| `chunkSize(int)` | `500` | Rows per queued job |
| `rowsPerReviewRequest(int)` | `2000` | Rows validated per poll in Review |
| `maxCollectedErrors(int)` | `50` | Failing rows listed in Review |
| `maxUploadSize(?int)` | `null` | Cap in KB |
| `fastValidation(bool)` | on | Compiled fast path in Review — [Testing](testing.md) |
| `parallelReview(bool, ?queue, ?connection)` | off | Review as queue jobs, one per slice — [Performance](performance.md) |
| `chunkedUploads(bool)` | `false` | [Resumable chunked upload](large-files.md) |
| `audit(bool)` | `false` | [Audit trail](audit-and-approval.md) |
| `requireApproval(bool\|Closure)` | `false` | Hold imports for approval (turns the audit on) |
| `canApproveUsing(Closure)` | Gate `approve-imports`, else any other user | Who may approve |
| `canViewAllAuditsUsing(Closure)` | Gate `view-all-import-audits`, else everyone | Who sees all audits |
| `allowSelfApproval(bool)` | `false` | Let the uploader approve their own import |

## Config file (`config/import-wizard.php`)

| Key | Env | Default | |
| --- | --- | --- | --- |
| `seen_store` | `IMPORT_WIZARD_SEEN_STORE` | `sqlite` | `sqlite` (one server) or `database` (shared) |
| `job_timeout` | `IMPORT_WIZARD_JOB_TIMEOUT` | `600` | Seconds a queued slice may run |
| `preview_rows` | `IMPORT_WIZARD_PREVIEW_ROWS` | `10` | Rows in the Map / Review preview tables |

Publish it with `php artisan vendor:publish --tag=import-wizard-config`. Translations: `--tag=import-wizard-translations` ([Localization](localization.md)).

## Console commands

| Command | |
| --- | --- |
| `import-wizard:run` | Validate and import a file already on a disk — [Large files](large-files.md) |
| `import-wizard:prune-uploads` | Remove abandoned chunked uploads |

## Events

`ImportAuditStatusChanged` — fired on every audit transition ([Audit & approval](audit-and-approval.md)).
