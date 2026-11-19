# Changelog

## 0.11.0
- **Open source**: the package is now public and released under the **MIT licence** (was proprietary / self-hosted only).
- CI runs on GitHub-hosted runners only; the `CI_RUNNER` override is gone.
- Docs: cover image in the README, Filament plugin directory kit (`art/filament-directory/`), logo, social preview and share images; the private-repository wording was removed.

## 0.10.1
- **Interop verified with the real libraries**: files written by maatwebsite/excel (xlsx/csv/xls/ods), spatie/simple-excel, rap2hpoutre/fast-excel and box/spout 3 (nikazooz/laravel-simplesheet's engine) import cleanly, and our exports read back in each; xlswriter-format files (XlsxWriter fixtures) too. New CI `interop` job; `docs/interop.md`.
- **Fixed**: formula cells were read as the formula text (`=D2*2`) instead of their cached result; legacy `.xls` dates arrived as Excel serial numbers and booleans as `TRUE` — now `Y-m-d H:i:s` and `1`/`0`.

## 0.10.0
- **Export**: `FileExport` and `ExportFileAction` — download records as Excel / CSV (or a blank template) in the importer's own layout, so an exported file imports straight back;
  `verify()` runs the exported file through the importer's rules. Streams in chunks (50k rows in ~2.4 s, flat memory); CSV formula-injection guard that the importer removes again.
- **Headless API**: `HeadlessImport` — the wizard's pipeline (normalise, map, review, import, audit) as a plain class for APIs, jobs and commands; `ReviewResult`, `ImportProgress`, `ImportBlockedException`.
- `CsvSource` strips the CSV formula guard apostrophe; the plugin works outside any panel; export strings translated (en, zh_CN, zh_TW, zh_HK).
- Tests: round-trip (xlsx / csv, tricky values), verify, template, formula guard, action; headless API suite; 50k export stress test. (110 tests.)

## 0.9.2
- CI: GitHub Actions for Pint, PHPStan level 10, the Pest matrix (PHP 8.2–8.4 × Laravel 12/13), a security job (security test group + `composer audit`), weekly deep checks (fuzz, mutation, stress) and Dependabot.
- New security tests (`composer security`): XSS escaping of cell values / audit notes, out-of-order wizard calls, locked state of the slide-over, importer-option whitelisting, chunk-upload confinement, approval and audit visibility authorisation.
- Hardening: only options the importer declares (`getOptionsFormComponents()`) are passed from the client-writable wizard state to the importer.

## 0.9.1
- Docs: new screenshots of the slide-over (list button, upload, map, review, done, refreshed table) in the README and `docs/slide-over.md`; all other screenshots refreshed.
- Video tutorial re-recorded (115 s) with a slide-over chapter and the Filament notifications.

## 0.9.0
- **`ImportWizardAction`**: open the wizard in a slide-over from a resource page / table / widget, like Filament's own import action (`docs/slide-over.md`).
- Optional Filament **notifications** for import start and finish (with a failed-rows link), `ImportWizardPlugin::notifications()` and `->notifications()` on the action; `import-wizard-finished` event to refresh tables.
- The wizard logic moved into `Concerns\ManagesImportWizard`, shared by `ImportWizardPage` and the new `Livewire\ImportWizardModal` (no behaviour change for the page).
- Demo: a Contacts list with the Import button.

## 0.8.2
- Review: each request now keeps validating slices for up to a second (`getReviewTimeBudget()`), so a 50,000-row file is reviewed in one or two requests instead of one poll per slice (~1.9 s end to end in a browser).
- Video tutorial re-recorded (80 s): adds the 50,000-row chunked upload + instant review and the Hong Kong Traditional Chinese interface; demo language switch `?lang=`.

## 0.8.1
- Fast validation path: verified far more thoroughly — differential fuzz vs Laravel (random rule sets × hostile values; ~1.6M comparisons in the large run), exhaustive single/pair rule × value tables, e-mail and numeric boundary tables, whole-pipeline (messages, attributes, casts) and real-import comparisons, and a 27-mutant mutation test (`composer fuzz`, `composer mutate`; docs/testing.md).
- **Fixed three correctness bugs found by it:** an empty array passed `required`; some long e-mail addresses were accepted that Laravel rejects; `INF` / exponent / over-precise numbers with `numeric` + `min`/`max` passed where Laravel throws.
- `->fastValidation(false)` on the plugin and `--no-fast` on `import-wizard:run` turn the fast path off.

## 0.8.0
- Review is ~19× faster: a compiled, conservative fast path (`FastRules`) decides "certainly passes" for common single-field rules natively; failures and anything unusual go to Laravel, so errors and messages are identical. 50k rows: 12.4 s → 0.65 s (77,000 rows/s); 200k rows: 49 s → 2.9 s.
- `Concerns\ValidatesQuickly` for importers: the same fast path inside the real import (50k rows 18.8 s → 8.5 s).
- Tests: exact-equivalence matrix (6,500 rows) and a 40,000-address e-mail fuzz against `email:rfc`.

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
