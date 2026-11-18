<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Checks;

/**
 * A check that looks at rows in the context of the whole file — something a
 * per-row validation rule cannot do (for example "no duplicates in the file").
 *
 * Checks run in the Review step (and the console command) against every row,
 * in file order. Implementations keep their own state between slices, keyed by
 * the run key, because Review is spread over many requests.
 */
interface RowCheck
{
    /**
     * Attach to a review run. A $fresh run starts from an empty state; a
     * resumed run (the next slice of the same review) keeps what it has.
     */
    public function begin(string $runKey, bool $fresh = true): void;

    /**
     * @param  array<string, mixed>  $data  the row keyed by importer column name
     * @return array<string, list<string>> error messages keyed by importer column name
     */
    public function check(array $data, int $rowNumber): array;

    /**
     * Release the state of a run.
     */
    public function finish(string $runKey): void;
}
