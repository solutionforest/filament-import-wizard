<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

/** Columns with examples, a list column and a custom header, to exercise the export formatting. */
class ExportableImporter extends Importer
{
    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')->requiredMapping()->rules(['required', 'max:80'])->example('Ana Ferreira'),
            ImportColumn::make('email')->rules(['required', 'email'])->examples(['ana@example.com', 'ben@example.com']),
            ImportColumn::make('phone')->exampleHeader('Phone Number')->example('+1 415 555 0101'),
            ImportColumn::make('tags')->array(';')->example('vip;newsletter'),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return null;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return '';
    }
}
