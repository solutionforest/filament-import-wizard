<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Jobs;

use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use InvalidArgumentException;
use SolutionForest\FilamentImportWizard\Support\Slice;

/**
 * Filament's ImportCsv job, but instead of carrying a serialized chunk of rows
 * in its payload it carries a {@see Slice} (a byte range) and reads its own rows
 * from the stored file. The queue payload stays tiny however big the file is.
 *
 * ImportDispatcher builds the job with `rows` = base64(serialize(descriptor)).
 */
class ImportCsvSlice extends ImportCsv
{
    protected Slice $slice;

    /**
     * @param  array<array<string, string>>|string  $rows
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(Import $import, array|string $rows, array $columnMap, array $options = [])
    {
        $descriptor = is_string($rows) ? unserialize(base64_decode($rows), ['allowed_classes' => false]) : $rows;

        if (! is_array($descriptor)) {
            throw new InvalidArgumentException('Invalid import slice descriptor.');
        }

        $this->slice = Slice::fromChunk($descriptor);

        parent::__construct($import, [], $columnMap, $options);
    }

    public function handle(): void
    {
        $this->rows = $this->slice->rows();

        parent::handle();
    }
}
