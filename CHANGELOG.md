# Changelog

## 0.7.0
- Review is faster: the duplicate check is batched per slice (SQLite transaction / one query per 1,000 keys): 50k rows 12.4 s → 9.0 s. `RowCheck::checkMany()` and `SeenStore::markSeenMany()` added (custom checks must implement them), `RowValidator::validateMany()`.
- Opt-in parallel Review on the queue: `->parallelReview()` (one `ReviewSlice` job per slice, results in `import_wizard_review_slices`).

## 0.6.0
- Stress suite (`composer stress`, `IMPORT_WIZARD_STRESS_ROWS`) and `docs/performance.md`: 50k / 200k rows, flat memory, exact counts.
- Full localization: audit page, status labels, error messages and JS messages moved to translation files; plural-aware counts, locale-aware numbers and dates, RTL-safe CSS.
- New locales: `zh_CN`, `zh_TW`, `zh_HK`; `import-wizard-translations` publish tag; `getEntityLabel()` hook; tests guard key parity and hard-coded text.

## 0.5.1
- Docs: supported-versions table, video tutorial, changelog.
- Dev dependencies now allow Testbench / Workbench 9–11 so the suite can run on Laravel 11, 12 and 13 (verified on 12 and 13).

## 0.5.0
- Preview tables: live in the Map step, first rows with per-row status in Review.
- Per-job timeout for queued slices (`import-wizard.job_timeout`) for Horizon; `preview_rows` config.
- README split into short README + `docs/`; demo README.

## 0.4.0
- `FieldRules` / `RuleContext`: one rules definition for Filament forms, importers and plain data.
- The audit trail is now opt-in (`->audit()`; `->requireApproval()` enables it).

## 0.3.0
- Audit trail with event history and `ImportAuditStatusChanged`; optional maker–checker approval.
- Resumable chunked uploads (no PHP size limit); `import-wizard:prune-uploads`.
- Pluggable seen-store (`sqlite` / `database`) for duplicate checks behind a load balancer.

## 0.2.0
- Reusable rule classes and `CommonRules`; `UniqueInFile` check; per-panel plugin.
- XLSX / ODS / XLS, sheet selection, UTF-16 / Windows-1252 CSV; `import-wizard:run` console command.
- PHPStan level 10.

## 0.1.0 (untagged first commit)
- First version: Upload → Map → Review → Import wizard, streamed validation, sliced queued import.
