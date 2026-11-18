<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Checks\Stores;

use PDO;
use RuntimeException;

/**
 * A throw-away SQLite file per run. Constant memory, no schema to install, but
 * local to one machine: use {@see DatabaseSeenStore} behind a load balancer.
 */
class SqliteSeenStore implements SeenStore
{
    protected ?PDO $pdo = null;

    protected string $runKey = '';

    public function __construct(protected ?string $directory = null) {}

    public function begin(string $runKey, bool $fresh): void
    {
        $this->runKey = $this->sanitize($runKey);
        $this->pdo = null;

        if ($fresh && is_file($this->path())) {
            unlink($this->path());
        }
    }

    public function markSeen(string $key): bool
    {
        $statement = $this->connection()->prepare('INSERT OR IGNORE INTO seen (hash) VALUES (?)');
        $statement->execute([hash('xxh128', $key, true)]);

        return $statement->rowCount() === 1;
    }

    public function finish(string $runKey): void
    {
        $this->pdo = null;
        $path = $this->directory().'/'.$this->sanitize($runKey).'.sqlite';

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
            throw new RuntimeException('SeenStore::begin() must be called before use.');
        }

        if (! is_dir($this->directory())) {
            mkdir($this->directory(), 0775, true);
        }

        $pdo = new PDO('sqlite:'.$this->path());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF');
        $pdo->exec('CREATE TABLE IF NOT EXISTS seen (hash BLOB PRIMARY KEY) WITHOUT ROWID');

        return $this->pdo = $pdo;
    }

    protected function path(): string
    {
        return $this->directory().'/'.$this->runKey.'.sqlite';
    }

    protected function directory(): string
    {
        return $this->directory ?? storage_path('app/import-wizard');
    }

    protected function sanitize(string $key): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    }
}
