<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Export;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use ReflectionProperty;
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;
use SolutionForest\FilamentImportWizard\Headless\ReviewResult;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export records to CSV or Excel using the columns of an **importer**, so what you export
 * can be imported straight back: the headers are the importer's own column names, values
 * are written in the forms the importer reads (ISO dates, `1`/`0` booleans, separator-joined
 * lists, related records by their lookup column), and {@see verify()} proves it by running the
 * exported file through the importer's rules.
 *
 *     FileExport::for(ContactImporter::class)
 *         ->query(Contact::query()->where('active', true))
 *         ->format('xlsx')
 *         ->download('contacts.xlsx');
 *
 * Rows are streamed from the database in chunks and written as they come, so memory stays
 * flat for any number of records.
 */
final class FileExport
{
    /** @var EloquentBuilder<Model>|Relation<Model, Model, mixed>|null */
    private mixed $query = null;

    private string $format = 'xlsx';

    /** @var list<string>|null */
    private ?array $only = null;

    /** @var array<string, Closure(Model): mixed> */
    private array $resolvers = [];

    private int $chunkSize = 1000;

    private bool $protectCsv = true;

    private string $sheetName = 'Export';

    private bool $template = false;

    /**
     * @param  class-string<Importer>  $importer
     */
    private function __construct(private readonly string $importer) {}

    /**
     * @param  class-string<Importer>  $importer
     */
    public static function for(string $importer): self
    {
        if (! is_subclass_of($importer, Importer::class)) {
            throw new InvalidArgumentException("[{$importer}] is not a Filament importer.");
        }

        return new self($importer);
    }

    /**
     * The records to export: any Eloquent query (filters, scopes, ordering) or relation.
     * Defaults to every record of the importer's model.
     *
     * @param  EloquentBuilder<Model>|Relation<Model, Model, mixed>  $query
     */
    public function query(EloquentBuilder|Relation $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * 'xlsx' (default) or 'csv'.
     */
    public function format(string $format): self
    {
        $format = strtolower($format);

        if (! in_array($format, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException("Unsupported export format [{$format}]: use xlsx or csv.");
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Export only these importer columns (by name), in this order.
     *
     * @param  list<string>  $names
     */
    public function columns(array $names): self
    {
        $known = array_map(fn (ImportColumn $column): string => $column->getName(), $this->importerColumns());

        foreach ($names as $name) {
            if (! in_array($name, $known, true)) {
                throw new InvalidArgumentException("Unknown importer column [{$name}].");
            }
        }

        $this->only = $names;

        return $this;
    }

    /**
     * How to read one column from a record, when it is not simply the attribute of the same name.
     *
     * @param  Closure(Model): mixed  $resolver
     */
    public function resolve(string $column, Closure $resolver): self
    {
        $this->resolvers[$column] = $resolver;

        return $this;
    }

    public function chunkSize(int $rows): self
    {
        $this->chunkSize = max(1, $rows);

        return $this;
    }

    public function sheetName(string $name): self
    {
        $this->sheetName = mb_substr(preg_replace('/[\[\]\*\?:\/\\\\]/', '', $name) ?: 'Export', 0, 31);

        return $this;
    }

    /**
     * CSV only: prefix values that a spreadsheet could execute as a formula (`=`, `+`, `-`, `@`)
     * with an apostrophe. On by default for downloads people open in Excel; turn it off for
     * machine-to-machine files. The importer removes this guard apostrophe when it reads a CSV
     * (see CsvSource), so a protected file still round-trips. XLSX cells are always written as
     * text and need no protection.
     */
    public function protectCsv(bool $protect = true): self
    {
        $this->protectCsv = $protect;

        return $this;
    }

    /**
     * Write a blank import template instead of data: the header row plus the importer's
     * example values.
     */
    public function template(bool $template = true): self
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return list<string> the header row, as the importer will map it back
     */
    public function headers(): array
    {
        return array_map(fn (ImportColumn $column): string => $column->getExampleHeader(), $this->selectedColumns());
    }

    /**
     * Stream the file to the browser.
     */
    public function download(?string $filename = null): StreamedResponse
    {
        $filename ??= 'export.'.$this->format;

        return response()->streamDownload(function (): void {
            $this->write('php://output');
        }, $filename, [
            'Content-Type' => $this->format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Write the file to a disk and return its path.
     */
    public function store(string $path, ?string $disk = null): string
    {
        $local = tempnam(sys_get_temp_dir(), 'iw-export-');

        if ($local === false) {
            throw new LogicException('Unable to create a temporary file.');
        }

        try {
            $this->write($local);
            $stream = fopen($local, 'r');

            if ($stream === false) {
                throw new LogicException('Unable to read the exported file.');
            }

            try {
                Storage::disk($disk)->put($path, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($local);
        }

        return $path;
    }

    /**
     * Prove the export round-trips: run the exported file through the importer's own rules
     * (nothing is imported). A valid result means every exported row would import cleanly.
     */
    public function verify(): ReviewResult
    {
        $directory = sys_get_temp_dir().'/iw-verify-'.bin2hex(random_bytes(6));
        $disk = 'iw-verify-'.basename($directory);
        $name = 'export.'.$this->format;

        mkdir($directory, 0775, true);
        config(["filesystems.disks.{$disk}" => ['driver' => 'local', 'root' => $directory]]);

        try {
            $this->store($name, $disk);

            return HeadlessImport::for($this->importer)->file($name, $disk)->autoMap()->review();
        } finally {
            File::deleteDirectory($directory);
            Storage::forgetDisk($disk);
            config(["filesystems.disks.{$disk}" => null]);
        }
    }

    private function write(string $target): void
    {
        $columns = $this->selectedColumns();
        $headers = array_map(fn (ImportColumn $column): string => $column->getExampleHeader(), $columns);

        if ($this->format === 'csv') {
            $writer = new CsvWriter;
            $writer->openToFile($target);
        } else {
            $writer = new XlsxWriter;
            $writer->openToFile($target);
            $writer->getCurrentSheet()->setName($this->sheetName);
        }

        try {
            $writer->addRow(Row::fromValues($headers));

            foreach ($this->template ? $this->templateRows($columns) : $this->records($columns) as $values) {
                $writer->addRow(Row::fromValues($this->safe($values)));
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * @param  list<ImportColumn>  $columns
     * @return \Generator<int, list<string>>
     */
    private function records(array $columns): \Generator
    {
        $query = $this->query ?? $this->defaultQuery();
        // lazyById() pages by primary key and would override a requested ordering: respect an explicit
        // ORDER BY (the caller's responsibility to make it deterministic) and use it otherwise.
        $ordered = $query instanceof Relation ? $query->getQuery()->getQuery()->orders : $query->getQuery()->orders;
        $records = $this->hasKey($query) && blank($ordered) ? $query->lazyById($this->chunkSize) : $query->lazy($this->chunkSize);

        foreach ($records as $record) {
            yield array_map(fn (ImportColumn $column): string => $this->formatValue($this->valueOf($record, $column), $column), $columns);
        }
    }

    /**
     * @param  list<ImportColumn>  $columns
     * @return \Generator<int, list<string>>
     */
    private function templateRows(array $columns): \Generator
    {
        $rows = $columns === [] ? 0 : max(array_map(fn (ImportColumn $column): int => count($column->getExamples()), $columns));

        for ($i = 0; $i < $rows; $i++) {
            yield array_map(function (ImportColumn $column) use ($i): string {
                $examples = array_values($column->getExamples());

                return $this->formatValue($examples[$i] ?? ($examples[0] ?? ''), $column);
            }, $columns);
        }
    }

    private function valueOf(Model $record, ImportColumn $column): mixed
    {
        $name = $column->getName();

        if (isset($this->resolvers[$name])) {
            return ($this->resolvers[$name])($record);
        }

        if ($column->hasRelationship()) {
            return $this->relationshipValue($record, $column);
        }

        return data_get($record, $name);
    }

    private function relationshipValue(Model $record, ImportColumn $column): mixed
    {
        $related = data_get($record, $column->getRelationshipName() ?? '');
        $lookup = (new ReflectionProperty(ImportColumn::class, 'resolveRelationshipUsing'))->getValue($column);
        $first = is_array($lookup) ? ($lookup[0] ?? null) : $lookup;
        $field = is_string($first) && $first !== '' ? $first : 'id';

        if ($related instanceof Model) {
            return data_get($related, $field);
        }

        if ($related instanceof Arrayable) {
            $related = $related->toArray();
        }

        if (is_array($related)) {
            return array_map(fn (mixed $item): mixed => data_get($item, $field), array_values($related));
        }

        return null;
    }

    /**
     * Write a value in the form the importer reads back.
     */
    private function formatValue(mixed $value, ImportColumn $column): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            return implode($column->getArraySeparator() ?? ',', array_map(fn (mixed $item): string => $this->formatValue($item, $column), array_values($value)));
        }

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function safe(array $values): array
    {
        if ($this->format !== 'csv' || ! $this->protectCsv) {
            return $values;
        }

        return array_map(
            fn (string $value): string => preg_match('/^[=+\-@\t\r]/', $value) === 1 && ! is_numeric($value) ? "'".$value : $value,
            $values,
        );
    }

    /**
     * @return list<ImportColumn>
     */
    private function selectedColumns(): array
    {
        $columns = $this->importerColumns();

        if ($this->only === null) {
            return $columns;
        }

        $byName = [];
        foreach ($columns as $column) {
            $byName[$column->getName()] = $column;
        }

        return array_map(fn (string $name): ImportColumn => $byName[$name], $this->only);
    }

    /**
     * @return list<ImportColumn>
     */
    private function importerColumns(): array
    {
        return array_values($this->importer::getColumns());
    }

    /**
     * @return EloquentBuilder<Model>
     */
    private function defaultQuery(): EloquentBuilder
    {
        return $this->importer::getModel()::query();
    }

    /**
     * @param  EloquentBuilder<Model>|Relation<Model, Model, mixed>  $query
     */
    private function hasKey(EloquentBuilder|Relation $query): bool
    {
        $model = $query instanceof Relation ? $query->getRelated() : $query->getModel();

        return $model->getKeyName() !== '' && $model->getIncrementing();
    }
}
