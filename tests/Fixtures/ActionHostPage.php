<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Pages\Page;
use SolutionForest\FilamentImportWizard\Actions\ImportWizardAction;

/** A page that hosts the import action in its header, like a resource list page would. */
class ActionHostPage extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static ?string $slug = 'host';

    protected function getHeaderActions(): array
    {
        return [ImportWizardAction::make()->importer(ContactImporter::class)];
    }
}
