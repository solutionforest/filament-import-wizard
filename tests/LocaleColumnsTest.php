<?php

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SolutionForest\FilamentImportWizard\Support\ColumnMapper;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Support\LocaleColumns;

/** Stand-in for a spatie/laravel-translatable model. */
class TranslatableStub extends Model
{
    /** @var array<string, array<string, mixed>> */
    public array $translations = [];

    public function setTranslation(string $key, string $locale, mixed $value): static
    {
        $this->translations[$key][$locale] = $value;

        return $this;
    }
}

class JsonCastStub extends Model
{
    protected $casts = ['title' => 'array'];
}

it('creates one import column per language', function () {
    $columns = LocaleColumns::make('title', ['en', 'zh_HK'])->label('Title')->rules(['max:5'])->requiredLocales(['en'])->columns();

    expect($columns)->toHaveCount(2)->each->toBeInstanceOf(ImportColumn::class)
        ->and($columns[0]->getName())->toBe('title_en')
        ->and($columns[0]->getLabel())->toBe('Title (en)')
        ->and($columns[0]->isMappingRequired())->toBeTrue()
        ->and($columns[0]->getDataValidationRules())->toBe(['required', 'max:5'])
        ->and($columns[1]->getName())->toBe('title_zh_HK')
        ->and($columns[1]->isMappingRequired())->toBeFalse()
        ->and($columns[1]->getDataValidationRules())->toBe(['max:5']);
});

it('auto-maps the language columns from the usual header spellings', function (array $headers) {
    $columns = LocaleColumns::make('title', ['en', 'ar'])->columns();

    expect((new ColumnMapper)->guess($columns, $headers))->toBe(['title_en' => $headers[0], 'title_ar' => $headers[1]]);
})->with([
    'snake' => [['title_en', 'title_ar']],
    'camel' => [['titleEn', 'titleAr']],
    'spaced' => [['Title EN', 'Title AR']],
    'dotted' => [['title.en', 'title.ar']],
    'bracketed' => [['Title (en)', 'Title (ar)']],
    'locale first' => [['en title', 'ar title']],
]);

class LocaleStubImporter extends Importer
{
    protected static ?string $model = TranslatableStub::class;

    public static function getColumns(): array
    {
        return LocaleColumns::make('title', ['en', 'ar'])->columns();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return '';
    }

    /** @param array<string, mixed> $data */
    public function fill(array $data, TranslatableStub $record): TranslatableStub
    {
        $this->data = $data;
        $this->record = $record;
        $this->fillRecord();

        return $record;
    }
}

it('fills the record through the importer, language by language', function () {
    $importer = new LocaleStubImporter(new Import, ['title_en' => 'title_en', 'title_ar' => 'title_ar'], []);

    $record = $importer->fill(['title_en' => 'Hello', 'title_ar' => 'مرحبا'], new TranslatableStub);

    expect($record->translations)->toBe(['title' => ['en' => 'Hello', 'ar' => 'مرحبا']]);

    // a language that is not mapped, or is blank, is left alone
    $partial = (new LocaleStubImporter(new Import, ['title_en' => 'title_en'], []))->fill(['title_en' => 'Hi', 'title_ar' => 'x'], new TranslatableStub);

    expect($partial->translations)->toBe(['title' => ['en' => 'Hi']]);
});

it('sets a language on a translatable model', function () {
    $record = new TranslatableStub;

    LocaleColumns::set($record, 'title', 'en', 'Hello');
    LocaleColumns::set($record, 'title', 'ar', 'مرحبا');
    LocaleColumns::set($record, 'title', 'fr', '');
    LocaleColumns::set($record, 'title', 'de', null);

    expect($record->translations)->toBe(['title' => ['en' => 'Hello', 'ar' => 'مرحبا']]);
});

it('merges languages into an array / JSON attribute and keeps existing ones', function () {
    $record = (new JsonCastStub)->setAttribute('title', ['en' => 'Old', 'fr' => 'Vieux']);

    LocaleColumns::set($record, 'title', 'en', 'New');
    LocaleColumns::set($record, 'title', 'ar', 'جديد');
    LocaleColumns::set($record, 'title', 'fr', '');

    expect($record->title)->toBe(['en' => 'New', 'fr' => 'Vieux', 'ar' => 'جديد']);
});

it('drops the empty tail spreadsheets pad rows with', function () {
    $sheet = new Spreadsheet;
    $worksheet = $sheet->getActiveSheet();
    $worksheet->fromArray([['name', 'email'], ['Ana', 'ana@x.com'], ['Ben', '']], null, 'A1');
    // styled-but-empty cells far to the right, as Excel leaves behind
    $worksheet->setCellValueExplicit([200, 1], '', 's');
    $worksheet->setCellValueExplicit([200, 2], '', 's');

    $local = tempnam(sys_get_temp_dir(), 'pad').'.xlsx';
    (new Xlsx($sheet))->save($local);
    Storage::disk('imports')->put('pad/padded.xlsx', file_get_contents($local));
    @unlink($local);

    $csv = (new FileNormalizer)->normalize('imports', 'pad/padded.xlsx');
    $source = new CsvSource('imports', $csv);

    expect($source->getHeaders())->toBe(['name', 'email']);

    $rows = iterator_to_array($source->read($source->getHeaders(), $source->getDataStartOffset(), 10), false);

    expect($rows)->toBe([['name' => 'Ana', 'email' => 'ana@x.com'], ['name' => 'Ben', 'email' => '']]);
});
