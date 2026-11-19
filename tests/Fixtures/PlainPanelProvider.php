<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;

/** A panel that registers the page directly, with no plugin: every default applies. */
class PlainPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('plain')->path('plain')->pages([ImportContacts::class]);
    }
}
