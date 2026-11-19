<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Console;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use RuntimeException;
use SolutionForest\FilamentImportWizard\Support\ColumnMapper;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;
use SolutionForest\FilamentImportWizard\Support\RowValidator;

/**
 * Server-side import for files too big to upload through the browser: put the
 * file on a disk (scp, rsync, a mounted volume) and run it with the same
 * mapping, validation and queued import as the wizard.
 */
class ImportCommand extends Command
{
    protected $signature = 'import-wizard:run
        {importer : Importer class, e.g. "App\\Filament\\Imports\\ContactImporter"}
        {path : Path of the CSV / XLSX / ODS file on the disk}
        {--disk= : Filesystem disk holding the file (defaults to the default disk)}
        {--map=* : Column mapping as importer_column=File Header (guessed when omitted)}
        {--user= : ID of the user the import is recorded for (required unless --dry-run)}
        {--sheet=0 : Zero-based sheet index for spreadsheets}
        {--chunk=500 : Rows per queued job}
        {--max-errors=20 : Failing rows to print}
        {--dry-run : Validate only, do not import}
        {--force : Import the valid rows even when some rows fail validation}';

    protected $description = 'Validate and import a (large) CSV or Excel file with a Filament importer.';

    public function handle(): int
    {
        $importer = $this->stringArgument('importer');

        if (! class_exists($importer) || ! is_subclass_of($importer, Importer::class)) {
            $this->components->error("[{$importer}] is not a Filament importer.");

            return self::FAILURE;
        }

        $diskOption = $this->option('disk');
        $defaultDisk = config('filesystems.default');
        $disk = is_string($diskOption) && $diskOption !== '' ? $diskOption : (is_string($defaultDisk) ? $defaultDisk : 'local');
        $path = $this->stringArgument('path');

        try {
            $csv = (new FileNormalizer)->normalize($disk, $path, (int) $this->option('sheet'));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $source = new CsvSource($disk, $csv);
        $headers = $source->getHeaders();
        $map = $this->resolveMap($importer, $headers);

        foreach ($importer::getColumns() as $column) {
            if ($column->isMappingRequired() && ! isset($map[$column->getName()])) {
                $this->components->error("No file column is mapped to required column [{$column->getName()}]. Use --map={$column->getName()}=Header.");

                return self::FAILURE;
            }
        }

        $total = $source->countRows();
        $this->components->info(number_format($total).' rows, mapped: '.json_encode($map));

        $runKey = Str::random(16);
        $validator = new RowValidator($importer, $map, [], [], $runKey);
        $offset = $source->getDataStartOffset();
        $invalid = 0;
        $row = 0;
        $maxErrors = (int) $this->option('max-errors');
        $bar = $this->output->createProgressBar($total);

        while ($row < $total) {
            $generator = $source->read($headers, $offset, 2000);
            $rows = iterator_to_array($generator, false);
            $read = count($rows);
            $results = $validator->validateMany($rows, $row + 1);

            foreach ($results as $errors) {
                $row++;
                $bar->advance();

                if ($errors === []) {
                    continue;
                }

                if (++$invalid <= $maxErrors) {
                    $bar->clear();
                    $this->line("Row {$row}: ".json_encode($errors));
                    $bar->display();
                }
            }

            $offset = $generator->getReturn();

            if ($read === 0) {
                break;
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($invalid > 0) {
            $this->components->warn(number_format($invalid).' of '.number_format($total).' rows have problems.');
        } else {
            $this->components->info('All rows passed validation.');
        }

        if ($this->option('dry-run')) {
            return $invalid > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($invalid > 0 && ! $this->option('force')) {
            $this->components->error('Nothing imported. Fix the file or pass --force to import the valid rows.');

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if ($user === null) {
            $this->components->error('Pass --user=<id> of an existing user so the import can be recorded.');

            return self::FAILURE;
        }

        $import = (new ImportStarter)->start($source, $importer, basename($path), $map, [], max(1, (int) $this->option('chunk')), $user);

        $this->components->info('Import #'.(is_scalar($import->getKey()) ? $import->getKey() : '?')." queued ({$import->total_rows} rows). Run a queue worker to process it.");

        return self::SUCCESS;
    }

    protected function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    /**
     * @param  class-string<Importer>  $importer
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    protected function resolveMap(string $importer, array $headers): array
    {
        $columns = array_values($importer::getColumns());
        $map = (new ColumnMapper)->guess($columns, $headers);
        $names = array_map(fn (ImportColumn $column): string => $column->getName(), $columns);
        $overrides = $this->option('map');

        foreach ($overrides as $pair) {
            if (! is_string($pair) || ! str_contains($pair, '=')) {
                continue;
            }

            [$name, $header] = explode('=', $pair, 2);

            if (in_array($name, $names, true) && in_array($header, $headers, true)) {
                $map[$name] = $header;
            }
        }

        return $map;
    }

    protected function resolveUser(): ?Authenticatable
    {
        $id = $this->option('user');
        $prototype = app(Authenticatable::class);

        if (! is_scalar($id)) {
            return null;
        }

        $user = $prototype->newQuery()->find($id);

        return $user instanceof Authenticatable ? $user : null;
    }
}
