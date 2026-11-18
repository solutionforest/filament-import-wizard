<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;
use LogicException;
use ReflectionProperty;
use SolutionForest\FilamentImportWizard\Checks\RowCheck;
use Throwable;

/**
 * Dry-runs the importer's own remap → cast → validate pipeline against rows
 * without resolving or saving any record, so what passes here is validated by
 * exactly the same rules as the real import. File-level {@see RowCheck}s (such
 * as duplicates within the file) are applied on top.
 */
class RowValidator
{
    protected Importer $importer;

    /**
     * @param  class-string<Importer>  $importer
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     * @param  list<RowCheck>  $checks
     */
    public function __construct(string $importer, array $columnMap, array $options = [], protected array $checks = [], string $runKey = '', bool $fresh = true)
    {
        $instance = app($importer, [
            'import' => new Import,
            'columnMap' => $columnMap,
            'options' => $options,
        ]);

        if (! $instance instanceof Importer) {
            throw new LogicException("[{$importer}] did not resolve to a Filament importer.");
        }

        $this->importer = $instance;

        foreach ($this->checks as $check) {
            $check->begin($runKey, $fresh);
        }
    }

    /**
     * @param  array<string, mixed>  $row  one file row keyed by file header
     * @return array<string, list<string>> importer column => messages (empty when valid)
     */
    public function validate(array $row, int $rowNumber = 0): array
    {
        $errors = [];

        try {
            $this->setState($row);

            foreach ($this->importer->getCachedColumns() as $column) {
                $column->clearResolvedRelatedRecords();
            }

            $this->importer->remapData();
            $this->importer->castData();
            $data = $this->getData($row);

            try {
                $this->importer->validateData();
            } catch (ValidationException $exception) {
                $errors = $this->normalize($exception->errors());
            }
        } catch (Throwable $exception) {
            return ['_row' => [$exception->getMessage()]];
        }

        foreach ($this->checks as $check) {
            foreach ($check->check($data, $rowNumber) as $column => $messages) {
                $errors[$column] = [...($errors[$column] ?? []), ...$messages];
            }
        }

        return $errors;
    }

    /**
     * The importer keeps the current row in protected properties that its own
     * `__invoke()` fills; the pipeline steps are public, so seed the row directly.
     *
     * @param  array<string, mixed>  $row
     */
    protected function setState(array $row): void
    {
        foreach (['originalData', 'data'] as $property) {
            (new ReflectionProperty(Importer::class, $property))->setValue($this->importer, $row);
        }

        (new ReflectionProperty(Importer::class, 'record'))->setValue($this->importer, null);
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    protected function getData(array $fallback): array
    {
        $data = (new ReflectionProperty(Importer::class, 'data'))->getValue($this->importer);

        if (! is_array($data)) {
            return $fallback;
        }

        $typed = [];

        foreach ($data as $key => $value) {
            $typed[(string) $key] = $value;
        }

        return $typed;
    }

    public function finish(string $runKey): void
    {
        foreach ($this->checks as $check) {
            $check->finish($runKey);
        }
    }

    /**
     * @param  array<array-key, mixed>  $errors
     * @return array<string, list<string>>
     */
    protected function normalize(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $column => $messages) {
            $normalized[(string) $column] = array_values(array_map(
                fn (mixed $message): string => is_scalar($message) ? (string) $message : '',
                (array) $messages,
            ));
        }

        return $normalized;
    }
}
