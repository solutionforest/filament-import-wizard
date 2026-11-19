<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

/** Columns come from a test-controlled definition: name => ['rules' => [...], 'cast' => null|'integer'|'numeric'|'boolean']. */
class DynamicImporter extends Importer
{
    protected static ?string $model = Contact::class;

    /** @var array<string, array{rules: array<mixed>, cast?: string|null, label?: string}> */
    public static array $definition = [];

    public static function getColumns(): array
    {
        $columns = [];

        foreach (static::$definition as $name => $definition) {
            $column = ImportColumn::make($name)->rules($definition['rules']);

            match ($definition['cast'] ?? null) {
                'integer' => $column->integer(),
                'numeric' => $column->numeric(),
                'boolean' => $column->boolean(),
                default => null,
            };

            if (isset($definition['label'])) {
                $column->label($definition['label'])->validationAttribute($definition['label']);
            }

            $columns[] = $column;
        }

        return $columns;
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
