<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;

class ParallelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('parallel')->path('parallel')->plugin(
            ImportWizardPlugin::make()->pages([ImportContacts::class])->parallelReview(allowSync: true),
        );
    }
}
