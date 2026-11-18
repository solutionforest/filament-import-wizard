<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Checks;

use PDO;
use RuntimeException;

/**
 * Flags rows whose value (or combination of values) already appeared earlier
 * in the same file. The seen-set lives in a throw-away SQLite file, so it uses
 * constant memory however many millions of rows the file has and survives the
 * many requests a Review run is spread across.
 */
class UniqueInFile implements RowCheck
{
    protected ?PDO $pdo = null;

    protected string $runKey = '';

    /**
     * @param  list<string>  $columns  importer column names that together must be unique
     */
    public function __construct(protected array $columns, protected ?string $directory = null) {}

    public function begin(string $runKey, bool $fresh = true): void
    {
        $this->runKey = $this->sanitize($runKey);
        $this->pdo = null;

        $path = $this->path();

        if ($fresh && is_file($path)) {
            unlink($path);
        }
    }

    public function check(array $data, int $rowNumber): array
    {
        $values = [];

        foreach ($this->columns as $column) {
            $value = $data[$column] ?? null;
            $values[] = is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';
        }

        // Blank keys are the job of `required`, not of a uniqueness check.
        if (array_filter($values, fn (string $value): bool => $value !== '') === []) {
            return [];
        }

        $statement = $this->connection()->prepare('INSERT OR IGNORE INTO seen (hash) VALUES (?)');
        $statement->execute([hash('xxh128', implode("\x1f", $values), true)]);

        if ($statement->rowCount() === 1) {
            return [];
        }

        $column = $this->columns[0];

        return [$column => [__('filament-import-wizard::rules.unique_in_file', [
            'attribute' => implode(' + ', $this->columns),
            'value' => implode(' + ', $values),
        ])]];
    }

    public function finish(string $runKey): void
    {
        $this->pdo = null;
        $path = $this->directory().'/'.$this->sanitize($runKey).'.'.$this->signature().'.sqlite';

        if (is_file($path)) {
            unlink($path);
        }
    }

    protected function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if ($this->runKey === '') {
            throw new RuntimeException('UniqueInFile::begin() must be called before check().');
        }

        $directory = $this->directory();

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdo = new PDO('sqlite:'.$this->path());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF');
        $pdo->exec('CREATE TABLE IF NOT EXISTS seen (hash BLOB PRIMARY KEY) WITHOUT ROWID');

        return $this->pdo = $pdo;
    }

    protected function path(): string
    {
        return $this->directory().'/'.$this->runKey.'.'.$this->signature().'.sqlite';
    }

    protected function directory(): string
    {
        return $this->directory ?? storage_path('app/import-wizard');
    }

    /** Two UniqueInFile checks on different columns must not share a file. */
    protected function signature(): string
    {
        return substr(md5(implode(',', $this->columns)), 0, 8);
    }

    protected function sanitize(string $key): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9]/', '', $key);
    }
}
