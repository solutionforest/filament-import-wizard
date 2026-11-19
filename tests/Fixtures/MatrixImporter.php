<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use SolutionForest\FilamentImportWizard\Rules\CommonRules;
use SolutionForest\FilamentImportWizard\Rules\PhoneNumber;

/** An importer exercising every supported rule kind plus rules the fast path must leave to Laravel. */
class MatrixImporter extends Importer
{
    protected static ?string $model = Contact::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('a')->rules(['required', 'string', 'max:10']),
            ImportColumn::make('b')->rules(['nullable', 'integer', 'min:1', 'max:100']),
            ImportColumn::make('c')->rules(CommonRules::email()),
            ImportColumn::make('d')->rules(['nullable', new PhoneNumber]),
            ImportColumn::make('e')->rules(['required', 'in:red,green,blue']),
            ImportColumn::make('f')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('g')->rules(['string', 'min:2']),
            ImportColumn::make('h')->rules(['nullable', 'email']),
            ImportColumn::make('i')->rules(['required', 'regex:/^x/']),         // not compilable: Laravel
            ImportColumn::make('j')->rules(['nullable', Rule::in(['p', 'q'])]), // not compilable: Laravel
            ImportColumn::make('k')->rules(['nullable', 'string', 'max:5']),
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
