# Installation

## 1. Require the package (private repository)

The package is distributed from our own repository only. Add it as a Composer VCS repository, then require it:

```bash
composer config repositories.filament-import-wizard vcs git@github.com:solutionforest/filament-import-wizard.git
composer require solution-forest/filament-import-wizard:^0.7
```

CI and deploy servers need a deploy key (or token) with read access to the repository.

Requirements: PHP 8.2+, Filament 5 (and the Laravel version it requires), `ext-pdo_sqlite`.

## 2. Database tables

```bash
php artisan make:queue-batches-table        # job_batches — skip if you already have it
php artisan vendor:publish --tag=filament-actions-migrations   # Filament's imports + failed_import_rows
php artisan migrate
```

The package's own migrations (`import_wizard_seen`, `import_wizard_audits`, …) are loaded automatically by `migrate`.
The audit tables stay empty unless you [turn the audit on](audit-and-approval.md).

## 3. A queue worker

Imports run on the queue, one job per slice of the file. Use a real queue driver (`redis`, `database`, `sqs`) and run workers:

```bash
php artisan queue:work --timeout=600
```

With `QUEUE_CONNECTION=sync` the whole import runs inside the web request — fine for trying it out, not for real files.
See [Scaling](scaling.md) for Horizon.

## 4. Optional

```bash
composer require phpoffice/phpspreadsheet     # to accept legacy .xls files
php artisan vendor:publish --tag=import-wizard-config   # config/import-wizard.php
```

Next: [Usage](usage.md).
