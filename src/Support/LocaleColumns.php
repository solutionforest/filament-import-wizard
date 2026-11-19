<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Closure;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One translatable field, one file column per language.
 *
 * A file with `title_en` and `title_zh_HK` columns becomes a single translated
 * `title` attribute. Each language is a normal import column (so mapping,
 * validation and the preview work as usual); this class only generates them and
 * writes the values into the record's translations. Works with spatie/laravel-translatable
 * (`setTranslation()`) and with any JSON / array-cast attribute.
 *
 * ```php
 * public static function getColumns(): array
 * {
 *     return [
 *         ...LocaleColumns::make('name', ['en', 'zh_HK'])
 *             ->label('Name')
 *             ->rules(['max:255'])
 *             ->requiredLocales(['en'])
 *             ->columns(),
 *     ];
 * }
 * ```
 */
class LocaleColumns
{
    protected ?string $label = null;

    /** @var array<mixed> */
    protected array $rules = [];

    /** @var list<string> */
    protected array $requiredLocales = [];

    /**
     * @param  list<string>  $locales
     */
    final public function __construct(protected string $field, protected array $locales) {}

    /**
     * @param  list<string>  $locales
     */
    public static function make(string $field, array $locales): static
    {
        return new static($field, $locales);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Rules applied to every language column.
     *
     * @param  array<mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Languages whose column must be mapped and filled.
     *
     * @param  list<string>  $locales
     */
    public function requiredLocales(array $locales): static
    {
        $this->requiredLocales = $locales;

        return $this;
    }

    /**
     * Name of the import column for a language: `title` + `en` → `title_en`.
     */
    public static function columnName(string $field, string $locale): string
    {
        return $field.'_'.$locale;
    }

    /**
     * @return list<ImportColumn>
     */
    public function columns(): array
    {
        return array_map(fn (string $locale): ImportColumn => $this->column($locale), $this->locales);
    }

    protected function column(string $locale): ImportColumn
    {
        $field = $this->field;
        $label = $this->label ?? Str::headline($field);
        $required = in_array($locale, $this->requiredLocales, true);

        return ImportColumn::make(self::columnName($field, $locale))
            ->label("{$label} ({$locale})")
            ->guess([
                "{$field} {$locale}",
                "{$field}.{$locale}",
                "{$field}-{$locale}",
                "{$field}({$locale})",
                "{$locale} {$field}",
                Str::camel("{$field}_{$locale}"),
            ])
            ->requiredMapping($required)
            ->rules($required ? ['required', ...$this->rules] : $this->rules)
            ->fillRecordUsing(self::filler($field, $locale));
    }

    /**
     * @return Closure(mixed, Model): void
     */
    protected static function filler(string $field, string $locale): Closure
    {
        return function (mixed $state, Model $record) use ($field, $locale): void {
            self::set($record, $field, $locale, $state);
        };
    }

    /**
     * Write one language of a translated attribute. A blank value leaves that
     * language untouched, so re-importing a partial file never wipes a translation.
     */
    public static function set(Model $record, string $field, string $locale, mixed $value): void
    {
        if (blank($value)) {
            return;
        }

        if (method_exists($record, 'setTranslation')) {
            $record->setTranslation($field, $locale, $value);

            return;
        }

        $current = $record->getAttribute($field);
        $current = is_array($current) ? $current : [];
        $current[$locale] = $value;
        $record->setAttribute($field, $current);
    }
}
