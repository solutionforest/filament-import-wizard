<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Filament\Actions\Imports\ImportDispatcher;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SolutionForest\FilamentImportWizard\Jobs\ImportCsvSlice;

class ImportStarter
{
    /**
     * Create the Import record and dispatch one queued batch job per slice,
     * through Filament's own dispatcher (so events, failed-rows CSV and the
     * completion notification behave exactly like the stock ImportAction).
     *
     * @param  class-string<Importer>  $importer
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     */
    public function start(
        CsvSource $source,
        string $importer,
        string $fileName,
        array $columnMap,
        array $options,
        int $chunkSize,
        ?Authenticatable $user,
        ?string $authGuard = null,
    ): Import {
        $headers = $source->getHeaders();
        $slices = $source->plan($chunkSize);
        $descriptors = [];

        foreach ($slices as $slice) {
            $descriptors[] = (new Slice($source->getDisk(), $source->getPath(), $source->getDelimiter(), $headers, $slice['offset'], $slice['count']))->toChunk();
        }

        $import = app(Import::class);
        $import->user()->associate($user instanceof Model ? $user : null);
        $import->file_name = $fileName;
        $import->file_path = $source->getPath();
        $import->importer = $importer;
        $import->total_rows = $slices->getReturn();
        $import->save();

        app(ImportDispatcher::class)->dispatch(
            import: $import,
            importChunks: $descriptors,
            columnMap: $columnMap,
            options: $options,
            job: ImportCsvSlice::class,
            authGuard: $authGuard,
        );

        return $import->refresh();
    }
}
