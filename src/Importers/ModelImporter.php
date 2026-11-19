<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Importers;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * An importer built from a model's table, for the "no importer class yet" quick start:
 *
 *     ImportWizardAction::make()->forModel(Product::class)
 *
 * One import column per fillable table column. Types and rules come from the schema:
 * integers, decimals and booleans are cast, dates get a `date` rule, `varchar(n)` a `max:n`
 * rule, a NOT NULL column without a default is required. Primary keys and timestamps are
 * skipped. Rows are created (never updated); write an importer class when you need more
 * (relationships, upserts, custom rules, `FieldRules`).
 *
 * Do not extend this yourself — use {@see ModelImporters::for()} for the generated subclass.
 */
abstract class ModelImporter extends Importer
{
    /**
     * @return list<ImportColumn>
     */
    public static function getColumns(): array
    {
        /** @var class-string<Model> $class */
        $class = static::getModel();
        $model = new $class;
        $columns = [];

        foreach (Schema::connection($model->getConnectionName())->getColumns($model->getTable()) as $column) {
            $name = $column['name'];

            if (self::isSkipped($model, $column) || ! $model->isFillable($name)) {
                continue;
            }

            $columns[] = self::column($name, $column);
        }

        return $columns;
    }

    public function resolveRecord(): ?Model
    {
        $class = static::getModel();

        return new $class;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $failed = $import->getFailedRowsCount();
        $count = number_format($import->successful_rows);

        return $failed > 0
            ? __('filament-import-wizard::import.model_importer.completed_with_failures', ['count' => $count, 'failed' => number_format($failed)])
            : __('filament-import-wizard::import.model_importer.completed', ['count' => $count]);
    }

    /**
     * @param  array{name: string, type_name: string, type: string, nullable: bool, default: mixed, auto_increment: bool}  $column
     */
    protected static function isSkipped(Model $model, array $column): bool
    {
        $name = $column['name'];

        return $name === $model->getKeyName()
            || $column['auto_increment']
            || in_array($name, [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn(), 'deleted_at', 'remember_token'], true);
    }

    /**
     * @param  array{name: string, type_name: string, type: string, nullable: bool, default: mixed, auto_increment: bool}  $column
     */
    protected static function column(string $name, array $column): ImportColumn
    {
        $type = Str::lower($column['type_name']);
        $full = Str::lower($column['type']);
        $required = ! $column['nullable'] && $column['default'] === null;
        $rules = [];

        $importColumn = ImportColumn::make($name)->label(Str::headline($name));

        if (preg_match('/^(tinyint\(1\)|bool|boolean)$/', $full) === 1 || $type === 'bool' || $type === 'boolean') {
            $importColumn->boolean();
            $rules[] = 'boolean';
        } elseif (preg_match('/int/', $type) === 1) {
            $importColumn->integer();
            $rules[] = 'integer';
        } elseif (preg_match('/decimal|numeric|float|double|real/', $type) === 1) {
            $importColumn->numeric();
            $rules[] = 'numeric';
        } elseif (preg_match('/^(date|datetime|timestamp|timestamptz|timestamp\b.*)$/', $type) === 1) {
            $rules[] = 'date';
        } elseif (preg_match('/^(?:var)?char\((\d+)\)/', $full, $match) === 1) {
            $rules[] = 'max:'.$match[1];
        }

        if (preg_match('/(^|_)e?mail($|_)/', $name) === 1) {
            $rules[] = 'email';
        }

        // A blank cell in a NOT NULL column means "use the default", never "write NULL".
        if (! $column['nullable']) {
            $importColumn->ignoreBlankState();
        }

        if ($required) {
            $importColumn->requiredMapping();
            array_unshift($rules, 'required');
        } else {
            $rules = ['nullable', ...$rules];
        }

        return $importColumn->rules($rules);
    }
}
