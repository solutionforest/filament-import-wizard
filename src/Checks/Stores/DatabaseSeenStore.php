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

    public function finish(string $runKey): void
    {
        $this->table()->where('run_key', $runKey)->delete();
    }

    protected function table(): Builder
    {
        return DB::connection($this->connection)->table('import_wizard_seen');
    }
}
