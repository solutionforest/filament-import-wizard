<?php

namespace Workbench\App\Filament\Pages;

use SolutionForest\FilamentImportWizard\Pages\ImportWizardPage;
use Workbench\App\Filament\Imports\ContactImporter;

class ImportContacts extends ImportWizardPage
{
    protected static ?string $importer = ContactImporter::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationLabel = 'Import contacts';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
}
