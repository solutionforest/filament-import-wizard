<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Headless;

use Closure;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use SolutionForest\FilamentImportWizard\Audit\AuditRecorder;
use SolutionForest\FilamentImportWizard\Checks\RowCheck;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use SolutionForest\FilamentImportWizard\Support\ColumnMapper;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;
use SolutionForest\FilamentImportWizard\Support\RowValidator;

/**
 * The wizard without a screen: the same normalise → map → review → import pipeline,
 * usable from an API controller, a queued job, a console command or a test.
 *
 *     $review = HeadlessImport::for(ContactImporter::class)
 *         ->file('imports/people.xlsx', disk: 's3')   // or ->upload($request->file('file'))
 *         ->autoMap()
 *         ->review();                                  // every row checked, nothing written
 *
 *     if ($review->isValid()) {
 *         $import = $headless->user($request->user())->start();   // queued, like the wizard
 *     }
 *
 * Streams the file in slices (flat memory at any size), uses the importer's own rules
 * (with the compiled fast path) and Filament's own queued importer. Approval flows are a
 * screen feature and are not part of the headless API; the audit trail can be recorded.
 */
final class HeadlessImport
{
    private ?string $disk = null;

    private ?string $path = null;

    private ?string $originalName = null;

    private int $sheet = 0;

    /** @var array<string, string> */
    private array $columnMap = [];

    /** @var array<string, mixed> */
    private array $options = [];

    /** @var list<RowCheck> */
    private array $checks = [];

    private int $chunkSize = 500;

    private int $rowsPerSlice = 2000;

    private int $maxErrors = 50;

    private int $previewRows = 10;

    private bool $fast = true;

    private bool $audit = false;

    private ?Authenticatable $user = null;

    private ?CsvSource $source = null;

    private ?string $normalizedPath = null;

    private ?ReviewResult $review = null;

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
     * Use a file that is already on a disk (CSV, XLSX, XLS or ODS).
     */
    public function file(string $path, ?string $disk = null): self
    {
        $this->disk = $disk ?? $this->defaultDisk();
        $this->path = $path;
        $this->originalName ??= basename($path);

        return $this->reset();
    }

    /**
     * Store an uploaded file (or a local file path) on the disk and use it.
     */
    public function upload(UploadedFile|string $file, ?string $disk = null, ?string $directory = null): self
    {
        $this->disk = $disk ?? $this->defaultDisk();
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xlsm', 'ods', 'xls'], true)) {
            throw new InvalidArgumentException('Unsupported file type.');
        }

        $safe = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), '.-');
        $directory = trim($directory ?? ImportWizardPlugin::current()->getDirectory(), '/');
        $stored = $directory.'/'.bin2hex(random_bytes(8)).'-'.($safe === '' ? 'upload.'.$extension : $safe);
        $stream = fopen($file instanceof UploadedFile ? (string) $file->getRealPath() : $file, 'r');

        if ($stream === false) {
            throw new InvalidArgumentException('The file cannot be read.');
        }

        try {
            Storage::disk($this->disk)->put($stored, $stream);
        } finally {
            fclose($stream);
        }

        $this->path = $stored;
        $this->originalName = $name;

        return $this->reset();
    }

    /**
     * Which sheet of a workbook to import (zero-based).
     */
    public function sheet(int $index): self
    {
        $this->sheet = max(0, $index);

        return $this->reset();
    }

    /**
     * The sheet names of a workbook (empty for CSV), to let an API client choose.
     *
     * @return list<string>
     */
    public function sheets(): array
    {
        return (new FileNormalizer)->sheets($this->requireDisk(), $this->requirePath());
    }

    /**
     * Match file columns to importer columns automatically.
     */
    public function autoMap(): self
    {
        $this->columnMap = (new ColumnMapper)->guess($this->columns(), $this->headers());
        $this->review = null;

        return $this;
    }

    /**
     * Set the mapping explicitly: importer column => file column header.
     *
     * @param  array<string, string>  $columnMap
     */
    public function map(array $columnMap): self
    {
        $names = array_map(fn (ImportColumn $column): string => $column->getName(), $this->columns());
        $headers = $this->headers();
        $map = [];

        foreach ($columnMap as $name => $header) {
            if (! in_array($name, $names, true)) {
                throw new InvalidArgumentException("Unknown importer column [{$name}].");
            }

            if (! in_array($header, $headers, true)) {
                throw new InvalidArgumentException("The file has no column [{$header}].");
            }

            $map[$name] = $header;
        }

        $this->columnMap = $map;
        $this->review = null;

        return $this;
    }

    /**
     * @return list<string> the header row of the file
     */
    public function headers(): array
    {
        return array_values(array_filter($this->source()->getHeaders(), fn (string $header): bool => $header !== ''));
    }

    /**
     * @return array<string, string> the current importer column => file column mapping
     */
    public function mapping(): array
    {
        return $this->columnMap;
    }

    /**
     * Labels of the required importer columns that have no file column yet.
     *
     * @return list<string>
     */
    public function missingRequiredColumns(): array
    {
        $missing = [];

        foreach ($this->columns() as $column) {
            if ($column->isMappingRequired() && ($this->columnMap[$column->getName()] ?? '') === '') {
                $missing[] = $column->getLabel() ?? Str::headline($column->getName());
            }
        }

        return $missing;
    }

    /**
     * Importer options; only the ones the importer declares are used.
     *
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): self
    {
        $declared = [];

        foreach ($this->importer::getOptionsFormComponents() as $component) {
            if (method_exists($component, 'getName') && is_string($name = $component->getName())) {
                $declared[] = $name;
            }
        }

        $this->options = array_intersect_key($options, array_flip($declared));
        $this->review = null;

        return $this;
    }

    /**
     * File-level checks, e.g. `new UniqueInFile(['email'])`.
     *
     * @param  list<RowCheck>  $checks
     */
    public function checks(array $checks): self
    {
        $this->checks = $checks;
        $this->review = null;

        return $this;
    }

    /**
     * The user the import is recorded for (and who gets Filament's completion notification).
     */
    public function user(?Authenticatable $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function chunkSize(int $rows): self
    {
        $this->chunkSize = max(1, $rows);

        return $this;
    }

    public function maxErrors(int $count): self
    {
        $this->maxErrors = max(1, $count);

        return $this;
    }

    public function previewRows(int $count): self
    {
        $this->previewRows = max(0, $count);

        return $this;
    }

    public function fastValidation(bool $enabled = true): self
    {
        $this->fast = $enabled;
        $this->review = null;

        return $this;
    }

    /**
     * Record this run in the audit trail (needs the audit migrations).
     */
    public function audit(bool $enabled = true): self
    {
        $this->audit = $enabled;

        return $this;
    }

    /**
     * Check every row (nothing is written). Memory stays flat however big the file is.
     *
     * @param  (Closure(int, int): mixed)|null  $progress  called with (rows checked, total rows) after each slice
     */
    public function review(?Closure $progress = null): ReviewResult
    {
        $missing = $this->missingRequiredColumns();

        if ($missing !== []) {
            throw new InvalidArgumentException('Map the required columns first: '.implode(', ', $missing).'.');
        }

        $source = $this->source();
        $headers = $source->getHeaders();
        $total = $source->countRows();
        $runKey = Str::random(16);
        $validator = new RowValidator($this->importer, $this->columnMap, $this->options, $this->checks, $runKey, fast: $this->fast);

        $offset = $source->getDataStartOffset();
        $row = 0;
        $invalid = 0;
        $byColumn = [];
        $sample = [];
        $preview = [];

        try {
            do {
                $generator = $source->read($headers, $offset, $this->rowsPerSlice);
                $rows = iterator_to_array($generator, false);
                $results = $validator->validateMany($rows, $row + 1);

                foreach ($rows as $index => $data) {
                    $errors = $results[$index];
                    $number = $row + $index + 1;

                    if ($number <= $this->previewRows) {
                        $preview[] = ['row' => $number, 'values' => $this->mapped($data), 'errors' => $errors];
                    }

                    if ($errors === []) {
                        continue;
                    }

                    $invalid++;

                    foreach (array_keys($errors) as $column) {
                        $byColumn[$column] = ($byColumn[$column] ?? 0) + 1;
                    }

                    if (count($sample) < $this->maxErrors) {
                        $sample[] = ['row' => $number, 'errors' => $errors];
                    }
                }

                $row += count($rows);
                $offset = $generator->getReturn();

                if ($progress !== null) {
                    $progress($row, $total);
                }
            } while (count($rows) === $this->rowsPerSlice && $row < $total);
        } finally {
            $validator->finish($runKey);
        }

        return $this->review = new ReviewResult($total, $invalid, $byColumn, $sample, $preview, $this->columnMap);
    }

    /**
     * Queue the import with Filament's own importer, one job per slice. Reviews first when
     * that has not been done. Refuses to import when rows have problems unless
     * `$allowInvalid` (the importer then skips the failing rows and records them as failed rows).
     *
     * @throws ImportBlockedException
     */
    public function start(bool $allowInvalid = false): Import
    {
        $review = $this->review ?? $this->review();

        if (! $allowInvalid && ! $review->isValid()) {
            throw new ImportBlockedException($review);
        }

        $source = $this->source();
        $audit = $this->audit ? $this->startAudit($source, $review) : null;
        $recorder = app(AuditRecorder::class);

        return (new ImportStarter)->start(
            source: $source,
            importer: $this->importer,
            fileName: $this->originalName ?? basename($this->requirePath()),
            columnMap: $this->columnMap,
            options: $this->options,
            chunkSize: $this->chunkSize,
            user: $this->user,
            onCreated: $audit instanceof ImportAudit ? fn (Import $import) => $recorder->started($audit, $import, $this->user) : null,
        );
    }

    private function startAudit(CsvSource $source, ReviewResult $review): ImportAudit
    {
        $recorder = app(AuditRecorder::class);
        $audit = $recorder->uploaded(
            null,
            $this->user,
            $this->importer,
            $this->originalName ?? basename($this->requirePath()),
            $this->requireDisk(),
            $this->requirePath(),
            $source->getPath(),
            $source->getDelimiter(),
            null,
        );

        $recorder->reviewed($audit, $this->columnMap, $this->options, $review->totalRows, $review->invalidRows, [
            'errors_by_column' => $review->errorsByColumn,
            'sample' => $review->sample,
        ]);

        return $audit;
    }

    /**
     * @return list<ImportColumn>
     */
    private function columns(): array
    {
        return array_values($this->importer::getColumns());
    }

    /**
     * @param  array<string, string>  $row  a file row keyed by header
     * @return array<string, string> importer column => value
     */
    private function mapped(array $row): array
    {
        $values = [];

        foreach ($this->columnMap as $name => $header) {
            $values[$name] = $row[$header] ?? '';
        }

        return $values;
    }

    private function source(): CsvSource
    {
        if ($this->source instanceof CsvSource) {
            return $this->source;
        }

        $this->normalizedPath = (new FileNormalizer)->normalize($this->requireDisk(), $this->requirePath(), $this->sheet);

        return $this->source = new CsvSource($this->requireDisk(), $this->normalizedPath);
    }

    private function reset(): self
    {
        $this->source = null;
        $this->normalizedPath = null;
        $this->columnMap = [];
        $this->review = null;

        return $this;
    }

    private function requireDisk(): string
    {
        return $this->disk ?? throw new LogicException('Call file() or upload() first.');
    }

    private function requirePath(): string
    {
        return $this->path ?? throw new LogicException('Call file() or upload() first.');
    }

    private function defaultDisk(): string
    {
        return ImportWizardPlugin::current()->getDisk();
    }
}
