<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use SolutionForest\FilamentImportWizard\Actions\ExportFileAction;
use SolutionForest\FilamentImportWizard\Export\FileExport;
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ActionHostPage;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ExportableImporter;

/**
 * @return list<array{name: string, email: string, phone: string}>
 */
function trickyContacts(int $extra = 100): array
{
    $rows = [
        ['name' => 'Ana, "the boss" Ferreira', 'email' => 'ana@x.com', 'phone' => '0071234567'],
        ['name' => "Line one\nLine two", 'email' => 'ben@x.com', 'phone' => '+1 415 555 0101'],
        ['name' => 'Zoë Müller 日本語 😀', 'email' => 'zoe@x.com', 'phone' => '00123456789'],
        ['name' => "O'Neil; semi", 'email' => 'neil@x.com', 'phone' => '(415) 555-0101'],
        ['name' => 'Plain', 'email' => 'plain@x.com', 'phone' => ''],
        ['name' => 'Long '.str_repeat('x', 45), 'email' => 'long@x.com', 'phone' => '0012345678901'],
        ['name' => '+Plus start', 'email' => 'plus@x.com', 'phone' => '+14155550101'],
    ];

    for ($i = 1; $i <= $extra; $i++) {
        $rows[] = ['name' => "Person {$i}", 'email' => "p{$i}@x.com", 'phone' => '+1 415 555 '.str_pad((string) $i, 4, '0', STR_PAD_LEFT)];
    }

    return $rows;
}

/** The file exactly as written (CsvSource would remove the formula guard, which these tests want to see). */
function exportedRows(string $path): array
{
    $handle = fopen(Storage::disk('imports')->path($path), 'r');
    $headers = fgetcsv($handle, null, ',', '"', '');
    $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF"); // the CSV writer adds a UTF-8 BOM so Excel reads it right
    $rows = [];

    while (($record = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $rows[] = array_combine($headers, $record);
    }

    fclose($handle);

    return $rows;
}

it('exports to Excel and CSV and imports straight back unchanged', function (string $format) {
    $originals = trickyContacts();
    foreach ($originals as $row) {
        Contact::create($row);
    }

    $path = FileExport::for(ContactImporter::class)->format($format)->store("exports/contacts.{$format}", 'imports');

    // Round trip: empty the table, import the exported file, compare every field.
    Contact::query()->delete();
    $headless = HeadlessImport::for(ContactImporter::class)->file($path, 'imports')->autoMap();
    $review = $headless->review();

    expect($review->isValid())->toBeTrue()->and($review->totalRows)->toBe(count($originals));

    $headless->user(User::forceCreate(['name' => 'Api']))->start();

    $roundTripped = Contact::query()->orderBy('id')->get(['name', 'email', 'phone'])->map->toArray()->all();

    // The importer turns an empty cell into null, as Filament's importers always do.
    $expected = array_map(fn (array $row): array => array_map(fn (string $value): ?string => $value === '' ? null : $value, $row), $originals);

    expect($roundTripped)->toBe($expected);
})->with(['xlsx', 'csv']);

it('uses the importer column names as headers so the mapping is automatic', function () {
    $export = FileExport::for(ExportableImporter::class);

    expect($export->headers())->toBe(['name', 'email', 'Phone Number', 'tags']);

    $subset = FileExport::for(ExportableImporter::class)->columns(['email', 'name']);
    expect($subset->headers())->toBe(['email', 'name']);
    expect(fn () => FileExport::for(ExportableImporter::class)->columns(['nope']))->toThrow(InvalidArgumentException::class);
});

it('writes values in the forms the importer reads: lists, booleans, dates, enums, resolved columns', function () {
    $contact = Contact::create(['name' => 'Ana', 'email' => 'ana@x.com', 'phone' => '1']);

    $enum = new class
    {
        public const VALUE = 'gold';
    };

    FileExport::for(ExportableImporter::class)->format('csv')
        ->resolve('tags', fn () => ['vip', 'news', true, new DateTimeImmutable('2024-03-05 10:20:30')])
        ->resolve('phone', fn () => null)
        ->store('exports/forms.csv', 'imports');

    $rows = exportedRows('exports/forms.csv');

    expect($rows[0])->toBe(['name' => 'Ana', 'email' => 'ana@x.com', 'Phone Number' => '', 'tags' => 'vip;news;1;2024-03-05 10:20:30']);
});

it('verifies that the exported rows would import cleanly, and shows the ones that would not', function () {
    Contact::create(['name' => 'Ana', 'email' => 'ana@x.com', 'phone' => '+1 415 555 0101']);
    expect(FileExport::for(ContactImporter::class)->verify()->isValid())->toBeTrue();

    // Data that is in the database but breaks the importer's rules (it would not survive a round trip).
    Contact::create(['name' => 'Broken', 'email' => 'not-an-email', 'phone' => 'call me']);

    $review = FileExport::for(ContactImporter::class)->verify();

    expect($review->isValid())->toBeFalse()
        ->and($review->invalidRows)->toBe(1)
        ->and($review->errorsByColumn)->toHaveKeys(['email', 'phone']);
});

it('exports only the records of a given query', function () {
    foreach (trickyContacts(10) as $row) {
        Contact::create($row);
    }

    FileExport::for(ContactImporter::class)->format('csv')->query(Contact::query()->where('name', 'like', 'Person%')->orderByDesc('id'))->store('exports/q.csv', 'imports');

    $rows = exportedRows('exports/q.csv');
    expect($rows)->toHaveCount(10)->and($rows[0]['name'])->toBe('Person 10');
});

it('writes a blank import template with the importer examples', function () {
    FileExport::for(ExportableImporter::class)->format('csv')->template()->store('exports/template.csv', 'imports');

    $rows = exportedRows('exports/template.csv');
    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['name' => 'Ana Ferreira', 'email' => 'ana@example.com', 'Phone Number' => "'+1 415 555 0101", 'tags' => 'vip;newsletter'])
        ->and($rows[1]['email'])->toBe('ben@example.com');

    // And the template itself imports.
    $review = HeadlessImport::for(ExportableImporter::class)->file('exports/template.csv', 'imports')->autoMap()->review();
    expect($review->isValid())->toBeTrue();
});

it('protects CSV downloads against formula injection, and only CSV', function () {
    Contact::create(['name' => '=HYPERLINK("http://evil","x")', 'email' => 'a@x.com', 'phone' => '+5']);
    Contact::create(['name' => '@SUM(A1)', 'email' => 'b@x.com', 'phone' => '-5']);

    FileExport::for(ContactImporter::class)->format('csv')->store('exports/safe.csv', 'imports');
    FileExport::for(ContactImporter::class)->format('csv')->protectCsv(false)->store('exports/raw.csv', 'imports');
    FileExport::for(ContactImporter::class)->format('xlsx')->store('exports/safe.xlsx', 'imports');

    $safe = exportedRows('exports/safe.csv');
    expect($safe[0]['name'])->toBe("'=HYPERLINK(\"http://evil\",\"x\")")
        ->and($safe[1]['name'])->toBe("'@SUM(A1)")
        ->and($safe[0]['phone'])->toBe('+5')->and($safe[1]['phone'])->toBe('-5') // plain numbers stay numbers
        ->and(exportedRows('exports/raw.csv')[0]['name'])->toBe('=HYPERLINK("http://evil","x")');

    $xlsx = HeadlessImport::for(ContactImporter::class)->file('exports/safe.xlsx', 'imports')->autoMap();
    expect($xlsx->review()->totalRows)->toBe(2);
})->group('security');

it('rejects formats and importers it does not know', function () {
    expect(fn () => FileExport::for(ContactImporter::class)->format('pdf'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FileExport::for(stdClass::class))->toThrow(InvalidArgumentException::class);
});

it('downloads from a header action, with the format chosen in a modal', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Contact::create(['name' => 'Ana', 'email' => 'ana@x.com', 'phone' => '1']);

    Livewire::test(ActionHostPage::class)
        ->callAction('exportFile', ['format' => 'csv'])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded();

    Livewire::test(ActionHostPage::class)
        ->callAction('exportFile', ['format' => 'xlsx', 'template' => true])
        ->assertFileDownloaded();

    expect(fn () => ExportFileAction::make()->getImporter())->toThrow(LogicException::class);
});
