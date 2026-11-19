<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Rules;

use Filament\Actions\Imports\ImportColumn;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * One place that says what a record's fields must look like — shared by the
 * Filament form and the importer, so they can never drift apart.
 *
 *     class ContactRules extends FieldRules
 *     {
 *         protected function define(RuleContext $context): array
 *         {
 *             return [
 *                 'name' => CommonRules::name(max: 80),
 *                 'email' => [...CommonRules::email(), Rule::unique('contacts')->ignore($context->record)],
 *                 'phone' => CommonRules::phone(),
 *             ];
 *         }
 *     }
 *
 *     // Filament form (resource or page):
 *     ContactRules::form([TextInput::make('name'), TextInput::make('email'), TextInput::make('phone')]);
 *
 *     // Importer:
 *     ContactRules::import([ImportColumn::make('name'), ImportColumn::make('email'), ImportColumn::make('phone')]);
 *
 *     // Anywhere else (API, console, tests):
 *     ContactRules::make()->validate($data);
 */
abstract class FieldRules
{
    /**
     * The rules per field. Called with the context they will run in, so a
     * definition can branch on `$context->isImport()` / `$context->record`.
     *
     * @return array<string, array<mixed>>
     */
    abstract protected function define(RuleContext $context): array;

    /**
     * Custom messages, keyed `field.rule` like Laravel's validator.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Human names of fields for messages, keyed by field.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    public static function make(): static
    {
        $instance = app(static::class);

        if (! $instance instanceof static) {
            throw new LogicException(static::class.' could not be resolved from the container.');
        }

        return $instance;
    }

    /**
     * The rules of one field in a context.
     *
     * @return array<mixed>
     */
    public function for(string $field, ?Model $record = null, bool $import = false): array
    {
        return $this->define(new RuleContext($record, $import))[$field] ?? [];
    }

    /**
     * Every field's rules in a context, ready for `Validator::make()`.
     *
     * @return array<string, array<mixed>>
     */
    public function all(?Model $record = null, bool $import = false): array
    {
        return $this->define(new RuleContext($record, $import));
    }

    /**
     * Apply the rules, messages and attribute names to Filament form fields,
     * matched by field name. Fields without rules are left untouched.
     *
     * @param  array<mixed>  $components
     * @return array<mixed>
     */
    public function toForm(array $components): array
    {
        $names = array_keys($this->all());

        foreach ($components as $component) {
            if (! $component instanceof Field || ! in_array($name = $component->getName(), $names, true)) {
                continue;
            }

            // The record is resolved lazily, so edit pages get unique-ignore for free.
            $component->rules(fn (?Model $record): array => array_values(array_filter(
                $this->for($name, $record),
                fn (mixed $rule): bool => $rule !== 'required',
            )));

            if (in_array('required', $this->for($name), true)) {
                $component->required();
            }

            $messages = $this->messagesFor($name);

            if ($messages !== []) {
                $component->validationMessages($messages);
            }

            if (isset($this->attributes()[$name])) {
                $component->validationAttribute($this->attributes()[$name]);
            }
        }

        return $components;
    }

    /**
     * Apply the rules to importer columns, matched by column name.
     *
     * @param  array<ImportColumn>  $columns
     * @return array<ImportColumn>
     */
    public function toImport(array $columns): array
    {
        $rules = $this->all(import: true);

        foreach ($columns as $column) {
            if (isset($rules[$column->getName()])) {
                $column->rules($rules[$column->getName()]);
            }

            if (isset($this->attributes()[$column->getName()])) {
                $column->validationAttribute($this->attributes()[$column->getName()]);
            }
        }

        return $columns;
    }

    /**
     * @param  array<mixed>  $components
     * @return array<mixed>
     */
    public static function form(array $components): array
    {
        return static::make()->toForm($components);
    }

    /**
     * @param  array<ImportColumn>  $columns
     * @return array<ImportColumn>
     */
    public static function import(array $columns): array
    {
        return static::make()->toImport($columns);
    }

    /**
     * Validate plain data (an API payload, a console input, a test) with the same rules.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the validated data
     *
     * @throws ValidationException
     */
    public function validate(array $data, ?Model $record = null, bool $import = false): array
    {
        $validated = [];

        foreach (Validator::make($data, $this->all($record, $import), $this->messages(), $this->attributes())->validate() as $key => $value) {
            $validated[(string) $key] = $value;
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    protected function messagesFor(string $field): array
    {
        $messages = [];

        foreach ($this->messages() as $key => $message) {
            if (str_starts_with($key, $field.'.')) {
                $messages[substr($key, strlen($field) + 1)] = $message;
            }
        }

        return $messages;
    }
}
