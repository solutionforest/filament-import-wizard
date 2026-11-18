<?php

namespace SolutionForest\FilamentImportWizard\Jobs;

use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use SolutionForest\FilamentImportWizard\Support\CsvSource;

/**
 * Filament's ImportCsv job, but instead of carrying a serialized chunk of rows
 * in its payload it carries a byte range and reads its own rows from the
 * stored file. The queue payload stays tiny however big the file is.
 *
 * `$rows` is the slice descriptor produced by {@see CsvSource::plan()}, plus
 * the storage location and headers, so ImportDispatcher can build it unchanged.
 */
class ImportCsvSlice extends ImportCsv
{
    /**
     * @param  array{disk: string, path: string, delimiter: string, headers: array<int, string>, offset: int, count: int}  $rows
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function __construct(Import $import, array|string $rows, array $columnMap, array $options = [])
    {
        // ImportDispatcher hands rows over as base64(serialize(...)).
        $this->slice = is_array($rows)
            ? $rows
            : unserialize(base64_decode($rows), ['allowed_classes' => false]);

        parent::__construct($import, [], $columnMap, $options);
    }

    /** @var array<string, mixed> */
    protected array $slice;

    public function handle(): void
    {
        $source = new CsvSource($this->slice['disk'], $this->slice['path'], $this->slice['delimiter']);

        $this->rows = iterator_to_array(
            $source->read($this->slice['headers'], $this->slice['offset'], $this->slice['count']),
            preserve_keys: false,
        );

        parent::handle();
    }
}
