<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use SolutionForest\FilamentImportWizard\Pages\ImportWizardPage;

class ImportContacts extends ImportWizardPage
{
    protected static ?string $importer = ContactImporter::class;

    protected function getUploadDisk(): string
    {
        return 'imports';
    }

    protected function getRowsPerReviewRequest(): int
    {
        return 10;
    }

    protected function getChunkSize(): int
    {
        return 10;
    }
}
