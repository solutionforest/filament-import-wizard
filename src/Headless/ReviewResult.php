<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Headless;

/**
 * The outcome of checking every row of a file before importing it.
 */
final readonly class ReviewResult
{
    /**
     * @param  array<string, int>  $errorsByColumn  importer column => number of rows with a problem in it
     * @param  list<array{row: int, errors: array<string, list<string>>}>  $sample  the first failing rows
     * @param  list<array{row: int, values: array<string, string>, errors: array<string, list<string>>}>  $preview  the first rows as the importer will see them
     * @param  array<string, string>  $mapping  importer column => file column
     */
    public function __construct(
        public int $totalRows,
        public int $invalidRows,
        public array $errorsByColumn,
        public array $sample,
        public array $preview,
        public array $mapping,
    ) {}

    public function validRows(): int
    {
        return $this->totalRows - $this->invalidRows;
    }

    public function isValid(): bool
    {
        return $this->invalidRows === 0;
    }

    /**
     * @return array{total_rows: int, valid_rows: int, invalid_rows: int, is_valid: bool, errors_by_column: array<string, int>, sample: list<array{row: int, errors: array<string, list<string>>}>, preview: list<array{row: int, values: array<string, string>, errors: array<string, list<string>>}>, mapping: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'valid_rows' => $this->validRows(),
            'invalid_rows' => $this->invalidRows,
            'is_valid' => $this->isValid(),
            'errors_by_column' => $this->errorsByColumn,
            'sample' => $this->sample,
            'preview' => $this->preview,
            'mapping' => $this->mapping,
        ];
    }
}
