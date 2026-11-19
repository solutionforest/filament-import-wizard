<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use ReflectionMethod;
use ReflectionProperty;
use SolutionForest\FilamentImportWizard\Checks\RowCheck;
use SolutionForest\FilamentImportWizard\Concerns\ValidatesQuickly;
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

    /** Compiled pre-check; null when disabled or the importer customises validateData(). */
    protected ?FastRules $fast = null;

    /** @var array<string, string> */
    protected array $messages = [];

    /** @var array<string, string> */
    protected array $attributes = [];

    /**
     * @param  class-string<Importer>  $importer
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     * @param  list<RowCheck>  $checks
     */
    public function __construct(string $importer, array $columnMap, array $options = [], protected array $checks = [], string $runKey = '', bool $fresh = true, bool $fast = true)
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

        // An importer that overrides validateData() has custom validation we must not bypass.
        $declaring = (new ReflectionMethod($instance, 'validateData'))->getDeclaringClass();
        $customValidation = $declaring->getName() !== Importer::class && ! in_array(ValidatesQuickly::class, class_uses($instance) ?: [], true);

        if ($fast && ! $customValidation) {
            $this->fast = FastRules::compile($instance->getValidationRules());
            $this->messages = $instance->getValidationMessages();
            $this->attributes = $instance->getValidationAttributes();
        }

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
        return $this->validateMany([$row], $rowNumber)[0];
    }

    /**
     * Validate a whole slice: the importer's rules row by row, then the file-level
     * checks once over the slice (so they can batch their storage access).
     *
     * @param  list<array<string, mixed>>  $rows  file rows keyed by file header
     * @return list<array<string, list<string>>> one error map per row, in order
     */
    public function validateMany(array $rows, int $firstRowNumber = 0): array
    {
        $errors = [];
        $mapped = [];

        foreach ($rows as $row) {
            [$rowErrors, $data] = $this->validateRules($row);

            $errors[] = $rowErrors;
            $mapped[] = $data;
        }

        foreach ($this->checks as $check) {
            foreach ($check->checkMany($mapped, $firstRowNumber) as $index => $checkErrors) {
                foreach ($checkErrors as $column => $messages) {
                    $errors[$index][$column] = [...($errors[$index][$column] ?? []), ...$messages];
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: array<string, list<string>>, 1: array<string, mixed>} errors and the importer's view of the row
     */
    protected function validateRules(array $row): array
    {
        try {
            $this->setState($row);

            foreach ($this->importer->getCachedColumns() as $column) {
                $column->clearResolvedRelatedRecords();
            }

            $this->importer->remapData();
            $this->importer->castData();
            $data = $this->getData($row);
            $errors = [];

            // Fast path: if every compiled field certainly passes, only the fields it
            // cannot judge go to Laravel. Anything failing (or doubtful) takes the full,
            // original path below, so messages are exactly Laravel's.
            if ($this->fast instanceof FastRules && $this->fast->passes($data)) {
                $slow = $this->fast->slowRules();

                return [$slow === [] ? [] : $this->laravelErrors($data, $slow), $data];
            }

            try {
                $this->importer->validateData();
            } catch (ValidationException $exception) {
                $errors = $this->normalize($exception->errors());
            }

            return [$errors, $data];
        } catch (Throwable $exception) {
            return [['_row' => [$exception->getMessage()]], $row];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, list<string>>
     */
    protected function laravelErrors(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules, $this->messages, $this->attributes);

        return $validator->fails() ? $this->normalize($validator->errors()->messages()) : [];
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
