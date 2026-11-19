<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

class ContactImporter extends Importer
{
    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        return ContactRules::import([
            ImportColumn::make('name')->requiredMapping(),
            ImportColumn::make('email')->guess(['mail']),
            ImportColumn::make('phone'),
        ]);
    }

    public function resolveRecord(): ?Model
    {
        return new Contact;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return "Imported {$import->successful_rows} rows.";
    }
}
