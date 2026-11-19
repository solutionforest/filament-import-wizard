<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Filament\Actions\Imports\Importer;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use SolutionForest\FilamentImportWizard\Jobs\ReviewSlice;

/**
 * Reviews a file as a batch of queue jobs, one per slice, so the time shrinks
 * with the number of workers instead of being one long sequential pass.
 * Results are collected from the `import_wizard_review_slices` table.
 */
class ParallelReview
{
    /**
     * @param  class-string<Importer>  $importer
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     * @return string the batch id
     */
    public function start(
        string $runKey,
        CsvSource $source,
        string $importer,
        array $columnMap,
        array $options,
        int $sliceRows,
        int $maxErrors,
        int $previewLimit,
        int|string|null $userId,
        ?string $guard,
        ?string $queue = null,
        ?string $connection = null,
    ): string {
        $this->forget($runKey);

        $headers = $source->getHeaders();
        $jobs = [];
        $firstRow = 1;

        foreach ($source->plan($sliceRows) as $index => $slice) {
            $descriptor = new Slice($source->getDisk(), $source->getPath(), $source->getDelimiter(), $headers, $slice['offset'], $slice['count']);

            $jobs[] = new ReviewSlice($runKey, $index, $firstRow, $descriptor->toChunk(), $importer, $columnMap, $options, $maxErrors, $previewLimit, $userId, $guard);
            $firstRow += $slice['count'];
        }

        $pending = Bus::batch($jobs)->allowFailures()->name('import-wizard-review');

        if ($queue !== null) {
            $pending->onQueue($queue);
        }

        if ($connection !== null) {
            $pending->onConnection($connection);
        }

        return $pending->dispatch()->id;
    }

    /**
     * @return array{finished: bool, failed: int, slices: int, rows: int, invalid: int, errorsByColumn: array<string, int>, sample: list<array{row: int, errors: array<string, list<string>>}>, preview: list<array{row: int, values: array<string, string>, errors: array<string, list<string>>}>}
     */
    public function collect(string $runKey, string $batchId, int $maxErrors): array
    {
        $batch = Bus::findBatch($batchId);

        $rows = 0;
        $invalid = 0;
        $byColumn = [];
        $sample = [];
        $preview = [];

        foreach (DB::table('import_wizard_review_slices')->where('run_key', $runKey)->orderBy('slice_index')->cursor() as $slice) {
            $rows += is_numeric($slice->rows) ? (int) $slice->rows : 0;
            $invalid += is_numeric($slice->invalid) ? (int) $slice->invalid : 0;

            foreach ($this->decode($slice->errors_by_column) as $column => $count) {
                if (is_numeric($count)) {
                    $byColumn[(string) $column] = ($byColumn[(string) $column] ?? 0) + (int) $count;
                }
            }

            if (count($sample) < $maxErrors) {
                foreach ($this->decode($slice->sample) as $entry) {
                    if (is_array($entry) && count($sample) < $maxErrors) {
                        /** @var array{row: int, errors: array<string, list<string>>} $entry */
                        $sample[] = $entry;
                    }
                }
            }

            if ($slice->preview !== null) {
                /** @var list<array{row: int, values: array<string, string>, errors: array<string, list<string>>}> $decoded */
                $decoded = $this->decode($slice->preview);
                $preview = $decoded;
            }
        }

        return [
            'finished' => $batch === null || $batch->finished(),
            'failed' => $batch === null ? 0 : $batch->failedJobs,
            'slices' => $batch === null ? 0 : $batch->totalJobs,
            'rows' => $rows,
            'invalid' => $invalid,
            'errorsByColumn' => $byColumn,
            'sample' => $sample,
            'preview' => $preview,
        ];
    }

    public function forget(string $runKey): void
    {
        DB::table('import_wizard_review_slices')->where('run_key', $runKey)->delete();
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function decode(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
