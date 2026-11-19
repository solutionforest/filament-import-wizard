<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard;

use Filament\Actions\Imports\Events\ImportCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Audit\AuditRecorder;
use SolutionForest\FilamentImportWizard\Console\ImportCommand;
use SolutionForest\FilamentImportWizard\Console\PruneUploadsCommand;
use SolutionForest\FilamentImportWizard\Importers\ModelImporters;
use SolutionForest\FilamentImportWizard\Livewire\ImportWizardModal;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;

class ImportWizardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/import-wizard.php', 'import-wizard');

        $this->commands([ImportCommand::class, PruneUploadsCommand::class]);

        ModelImporters::register();
    }

    public function boot(): void
    {
        Event::listen(ImportCompleted::class, function (ImportCompleted $event): void {
            $import = $event->getImport();
            // Filament fires this for every import in the app, wizard or not.
            if (! Schema::hasTable('import_wizard_audits')) {
                return;
            }

            $audit = ImportAudit::query()->where('import_id', $import->getKey())->first();

            if ($audit instanceof ImportAudit && ! $audit->status->isFinal()) {
                app(AuditRecorder::class)->completed($audit, $import);
            }
        });

        Livewire::component('import-wizard-modal', ImportWizardModal::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../config/import-wizard.php' => config_path('import-wizard.php')], 'import-wizard-config');
        $this->publishes([__DIR__.'/../resources/lang' => lang_path('vendor/filament-import-wizard')], 'import-wizard-translations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-import-wizard');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-import-wizard');
    }
}
