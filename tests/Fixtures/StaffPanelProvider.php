<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('staff')->path('staff')->plugin(
            ImportWizardPlugin::make()
                ->pages([ImportContacts::class])
                ->disk('imports')
                ->directory('staff-imports')
                ->chunkSize(7)
                ->maxCollectedErrors(2),
        );
    }
}
