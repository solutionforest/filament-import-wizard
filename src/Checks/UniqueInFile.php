<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Checks;

use SolutionForest\FilamentImportWizard\Checks\Stores\DatabaseSeenStore;
use SolutionForest\FilamentImportWizard\Checks\Stores\SeenStore;
use SolutionForest\FilamentImportWizard\Checks\Stores\SqliteSeenStore;

/**
 * Flags rows whose value (or combination of values) already appeared earlier
 * in the same file. The seen-set lives in a {@see SeenStore} outside the PHP
 * process, so memory stays constant however many millions of rows the file has
 * and the state survives the many requests a Review run is spread across.
 *
 * The store defaults to `config('import-wizard.seen_store')`; behind a load
 * balancer use `database` (or pass a custom store).
 */
class UniqueInFile implements RowCheck
{
    protected SeenStore $store;

    /**
     * @param  list<string>  $columns  importer column names that together must be unique
     */
    public function __construct(protected array $columns, ?SeenStore $store = null)
    {
        $this->store = $store ?? (config('import-wizard.seen_store') === 'database' ? new DatabaseSeenStore : new SqliteSeenStore);
    }

    public function begin(string $runKey, bool $fresh = true): void
    {
        $this->store->begin($runKey.'-'.$this->signature(), $fresh);
    }

    public function check(array $data, int $rowNumber): array
    {
        return $this->checkMany([$data], $rowNumber)[0];
    }

    public function checkMany(array $rows, int $firstRowNumber): array
    {
        $keys = [];
        $values = [];

        foreach ($rows as $index => $data) {
            $parts = [];

            foreach ($this->columns as $column) {
                $value = $data[$column] ?? null;
                $parts[] = is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';
            }

            // Blank keys are the job of `required`, not of a uniqueness check.
            if (array_filter($parts, fn (string $part): bool => $part !== '') !== []) {
                $keys[$index] = implode("\x1f", $parts);
                $values[$index] = $parts;
            }
        }

        $isNew = $keys === [] ? [] : array_combine(array_keys($keys), $this->store->markSeenMany(array_values($keys)));
        $results = [];

        foreach ($rows as $index => $_) {
            if (! isset($keys[$index]) || $isNew[$index]) {
                $results[] = [];

                continue;
            }

            $results[] = [$this->columns[0] => [__('filament-import-wizard::rules.unique_in_file', [
                'attribute' => implode(' + ', $this->columns),
                'value' => implode(' + ', $values[$index]),
            ])]];
        }

        return $results;
    }

    public function finish(string $runKey): void
    {
        $this->store->finish($runKey.'-'.$this->signature());
    }

    /** Two UniqueInFile checks on different columns must not share state. */
    protected function signature(): string
    {
        return substr(md5(implode(',', $this->columns)), 0, 8);
    }
}
