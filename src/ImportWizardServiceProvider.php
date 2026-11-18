<?php

namespace SolutionForest\FilamentImportWizard;

use Illuminate\Support\ServiceProvider;

class ImportWizardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-import-wizard');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-import-wizard');
    }
}
