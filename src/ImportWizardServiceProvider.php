<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard;

use Illuminate\Support\ServiceProvider;
use SolutionForest\FilamentImportWizard\Console\ImportCommand;

class ImportWizardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([ImportCommand::class]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-import-wizard');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-import-wizard');
    }
}
