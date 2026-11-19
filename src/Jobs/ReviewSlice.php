<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Jobs;

use Filament\Actions\Imports\Importer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Support\Slice;

/**
 * Validates one slice of the file with the importer's own rules and stores the
 * summary (counts, the first failing rows, a preview) for the page to collect.
 * Slices are independent, so any number of workers can review a file in parallel.
 */
class ReviewSlice implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    /**
     * @param  list<array<string, string>>  $chunk  a {@see Slice::toChunk()} descriptor
     * @param  class-string<Importer>  $importer
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $runKey,
        public int $sliceIndex,
        public int $firstRow,
        public array $chunk,
        public string $importer,
        public array $columnMap,
        public array $options,
        public int $maxErrors,
        public int $previewLimit,
        public int|string|null $userId = null,
        public ?string $guard = null,
    ) {
        $timeout = config('import-wizard.job_timeout', 600);
        $this->timeout = is_numeric($timeout) ? max(1, (int) $timeout) : 600;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $guard = auth()->guard($this->guard);
        $previous = $guard->user();

        if ($this->userId !== null && ! $previous instanceof Authenticatable) {
            $user = app(Authenticatable::class)->newQuery()->find($this->userId);

            if ($user instanceof Authenticatable) {
                $guard->setUser($user);
            }
        }

        try {
            $this->review();
        } finally {
            if (! $previous instanceof Authenticatable && method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }
    }

    protected function review(): void
    {
        $rows = Slice::fromChunk($this->chunk)->rows();
        $results = (new RowValidator($this->importer, $this->columnMap, $this->options))->validateMany($rows, $this->firstRow);

        $invalid = 0;
        $byColumn = [];
        $sample = [];
        $preview = [];

        foreach ($rows as $index => $row) {
            $errors = $results[$index];
            $rowNumber = $this->firstRow + $index;

            if ($this->sliceIndex === 0 && $index < $this->previewLimit) {
                $values = [];

                foreach ($this->columnMap as $name => $header) {
                    $values[$name] = $row[$header] ?? '';
                }

                $preview[] = ['row' => $rowNumber, 'values' => $values, 'errors' => $errors];
            }

            if ($errors === []) {
                continue;
            }

            $invalid++;

            foreach (array_keys($errors) as $column) {
                $byColumn[$column] = ($byColumn[$column] ?? 0) + 1;
            }

            if (count($sample) < $this->maxErrors) {
                $sample[] = ['row' => $rowNumber, 'errors' => $errors];
            }
        }

        DB::table('import_wizard_review_slices')->updateOrInsert(
            ['run_key' => $this->runKey, 'slice_index' => $this->sliceIndex],
            [
                'first_row' => $this->firstRow,
                'rows' => count($rows),
                'invalid' => $invalid,
                'errors_by_column' => json_encode($byColumn, JSON_THROW_ON_ERROR),
                'sample' => json_encode($sample, JSON_THROW_ON_ERROR),
                'preview' => $this->sliceIndex === 0 ? json_encode($preview, JSON_THROW_ON_ERROR) : null,
            ],
        );
    }
}
