<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Checks\Stores;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the seen-set in the application database (table `import_wizard_seen`,
 * created by the package migration), so any web server can serve any slice of
 * a Review run.
 */
class DatabaseSeenStore implements SeenStore
{
    protected string $runKey = '';

    public function __construct(protected ?string $connection = null) {}

    public function begin(string $runKey, bool $fresh): void
    {
        $this->runKey = $runKey;

        if ($fresh) {
            $this->table()->where('run_key', $runKey)->delete();
        }
    }

    public function markSeen(string $key): bool
    {
        return $this->table()->insertOrIgnore([
            'run_key' => $this->runKey,
            'hash' => hash('xxh128', $key),
        ]) === 1;
    }

    public function markSeenMany(array $keys): array
    {
        $hashes = array_map(fn (string $key): string => hash('xxh128', $key), $keys);
        $existing = [];

        // One query per 1,000 keys instead of one per row.
        foreach (array_chunk(array_values(array_unique($hashes)), 1000) as $chunk) {
            foreach ($this->table()->where('run_key', $this->runKey)->whereIn('hash', $chunk)->pluck('hash') as $hash) {
                if (is_string($hash)) {
                    $existing[$hash] = true;
                }
            }
        }

        $result = [];
        $fresh = [];

        foreach ($hashes as $hash) {
            $isNew = ! isset($existing[$hash]);
            $result[] = $isNew;

            if ($isNew) {
                $existing[$hash] = true;
                $fresh[] = ['run_key' => $this->runKey, 'hash' => $hash];
            }
        }

        foreach (array_chunk($fresh, 1000) as $chunk) {
            $this->table()->insertOrIgnore($chunk);
        }

        return $result;
    }

    public function finish(string $runKey): void
    {
        $this->table()->where('run_key', $runKey)->delete();
    }

    protected function table(): Builder
    {
        return DB::connection($this->connection)->table('import_wizard_seen');
    }
}
