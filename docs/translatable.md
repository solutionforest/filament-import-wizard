# Translatable fields (one column per language)

Files often carry one column per language: `title_en`, `title_zh_HK`, `title_ar`. `LocaleColumns` turns them into **one translated attribute** on your model, with normal mapping, validation and preview for every language.

```php
use SolutionForest\FilamentImportWizard\Support\LocaleColumns;

public static function getColumns(): array
{
    return [
        ImportColumn::make('sku')->requiredMapping()->rules(['required']),

        ...LocaleColumns::make('name', ['en', 'zh_HK', 'ar'])
            ->label('Name')                  // shown as "Name (en)", "Name (zh_HK)", …
            ->rules(['max:255'])             // applied to every language
            ->requiredLocales(['en'])        // "en" must be mapped and filled
            ->columns(),
    ];
}
```

## What it does

- Creates one import column per language, named `name_en`, `name_zh_HK`, … Each one is a normal Filament `ImportColumn`.
- **Auto-maps** the usual header spellings: `name_en`, `nameEn`, `Name EN`, `name.en`, `Name (en)`, `en name`.
- On import each language is written into the record's translations:
  - with [spatie/laravel-translatable](https://github.com/spatie/laravel-translatable) (or anything that has `setTranslation()`) it calls `setTranslation('name', 'en', …)`;
  - otherwise it merges into an array / JSON-cast attribute (`$record->name = ['en' => …, 'ar' => …]`).
- A **blank cell leaves that language untouched**, so re-importing a partial file never wipes an existing translation.

`LocaleColumns::set($record, 'name', 'en', 'Hello')` does the same write by hand if you need it in a custom `fillRecord()`.

## Notes

- The attribute (`name`) must be translatable or array / JSON-cast on the model; the generated columns never write to `name_en` itself.
- Export: add the same languages to your exporter's columns the way you would any column; the round-trip header names are `name_en`, … so the file maps straight back.
