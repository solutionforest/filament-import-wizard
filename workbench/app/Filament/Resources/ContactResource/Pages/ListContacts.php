<?php

namespace Workbench\App\Filament\Resources\ContactResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;
use SolutionForest\FilamentImportWizard\Actions\ExportFileAction;
use SolutionForest\FilamentImportWizard\Actions\ImportWizardAction;
use Workbench\App\Filament\Imports\ContactImporter;
use Workbench\App\Filament\Resources\ContactResource;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Download what the table shows (filters and sorting included) as Excel / CSV, in the layout the importer reads back.
            ExportFileAction::make()->importer(ContactImporter::class),
            // The same wizard as the full page, in a slide-over, with start / finished notifications.
            ImportWizardAction::make()->importer(ContactImporter::class),
        ];
    }

    /** The wizard announces when an import has finished: re-render the table so the new rows show. */
    #[On('import-wizard-finished')]
    public function refreshAfterImport(): void {}
}
