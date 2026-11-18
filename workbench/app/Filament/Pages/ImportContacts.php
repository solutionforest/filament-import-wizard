<?php

namespace Workbench\App\Filament\Pages;

use SolutionForest\FilamentImportWizard\Checks\RowCheck;
use SolutionForest\FilamentImportWizard\Checks\UniqueInFile;
use SolutionForest\FilamentImportWizard\Pages\ImportWizardPage;
use Workbench\App\Filament\Imports\ContactImporter;

class ImportContacts extends ImportWizardPage
{
    protected static ?string $importer = ContactImporter::class;

    protected static bool $shouldRegisterNavigation = true;

    /**
     * @return list<RowCheck>
     */
    protected function getRowChecks(): array
    {
        return [new UniqueInFile(['email'])];
    }

    protected static ?string $navigationLabel = 'Import contacts';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
}
