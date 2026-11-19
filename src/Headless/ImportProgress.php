<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Headless;

use Filament\Actions\Imports\Models\Import;

/**
 * A snapshot of a running or finished import, shaped for an API response.
 */
final readonly class ImportProgress
{
    public function __construct(
        public int $id,
        public int $totalRows,
        public int $processedRows,
        public int $successfulRows,
        public int $failedRows,
        public bool $finished,
    ) {}

    public static function of(Import $import): self
    {
        $key = $import->getKey();

        return new self(
            is_int($key) ? $key : 0,
            $import->total_rows,
            $import->processed_rows,
            $import->successful_rows,
            $import->completed_at === null ? max(0, $import->processed_rows - $import->successful_rows) : $import->getFailedRowsCount(),
            $import->completed_at !== null,
        );
    }

    public function percent(): float
    {
        return $this->totalRows === 0 ? 100.0 : round(min(100, $this->processedRows / $this->totalRows * 100), 1);
    }

    /**
     * @return array{id: int, status: string, total_rows: int, processed_rows: int, successful_rows: int, failed_rows: int, percent: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->finished ? 'finished' : 'running',
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'successful_rows' => $this->successfulRows,
            'failed_rows' => $this->failedRows,
            'percent' => $this->percent(),
        ];
    }
}
