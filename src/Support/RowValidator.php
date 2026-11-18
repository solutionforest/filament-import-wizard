<?php

namespace SolutionForest\FilamentImportWizard\Support;

use Closure;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dry-runs the importer's own remap → cast → validate pipeline against rows
 * without resolving or saving any record, so what passes here is validated by
 * exactly the same rules as the real import.
 */
class RowValidator
{
    protected Importer $importer;

    /**
     * @param  class-string<Importer>  $importer
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(string $importer, array $columnMap, array $options = [])
    {
        $this->importer = app($importer, [
            'import' => new Import,
            'columnMap' => $columnMap,
            'options' => $options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, array<int, string>> importer column => messages (empty when valid)
     */
    public function validate(array $row): array
    {
        try {
            (Closure::bind(function (array $row): void {
                $this->originalData = $this->data = $row;
                $this->record = null;

                foreach ($this->getCachedColumns() as $column) {
                    $column->clearResolvedRelatedRecords();
                }

                $this->remapData();
                $this->castData();
                $this->validateData();
            }, $this->importer, Importer::class))($row);
        } catch (ValidationException $exception) {
            return $exception->errors();
        } catch (Throwable $exception) {
            return ['_row' => [$exception->getMessage()]];
        }

        return [];
    }
}
