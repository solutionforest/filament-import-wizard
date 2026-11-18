<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use SolutionForest\FilamentImportWizard\Support\ColumnMapper;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

it('reads headers, strips the BOM and guesses the delimiter', function () {
    storeCsv([['Full Name', 'E-mail'], ['Ana', 'a@x.com']], 'a.csv', ';', "\xEF\xBB\xBF");

    $source = new CsvSource('imports', 'a.csv');

    expect($source->getDelimiter())->toBe(';')
        ->and($source->getHeaders())->toBe(['Full Name', 'E-mail']);
});

it('reads slices by byte offset, including multi-line quoted fields and blank lines', function () {
    storeCsv([['n', 'v'], ['1', "multi\nline"], ['2', 'b'], ['3', 'c'], ['4', 'd']], 'b.csv');
    Storage::disk('imports')->append('b.csv', '');

    $source = new CsvSource('imports', 'b.csv');
    $plan = iterator_to_array($source->plan(2));

    expect($plan)->toHaveCount(2)
        ->and(array_column($plan, 'count'))->toBe([2, 2]);

    $rows = iterator_to_array($source->read(['n', 'v'], $plan[1]['offset'], $plan[1]['count']));

    expect(array_column($rows, 'n'))->toBe(['3', '4']);
    expect($source->countRows())->toBe(4);
});

it('maps columns exactly, by normalised name, by guess and fuzzily without reusing a file column', function () {
    $map = (new ColumnMapper)->guess(
        ContactImporter::getColumns(),
        ['Full name', 'mail', 'Phone Nr', 'Notes'],
    );

    expect($map)->toEqualCanonicalizing(['name' => 'Full name', 'email' => 'mail', 'phone' => 'Phone Nr']);

    $map = (new ColumnMapper)->guess(ContactImporter::getColumns(), ['Name', 'E-Mail', 'phone']);

    expect($map)->toEqualCanonicalizing(['name' => 'Name', 'email' => 'E-Mail', 'phone' => 'phone']);
});

it('validates rows with the importer rules without saving', function () {
    $validator = new RowValidator(ContactImporter::class, ['name' => 'n', 'email' => 'e']);

    expect($validator->validate(['n' => 'Ana', 'e' => 'ana@x.com']))->toBe([])
        ->and($validator->validate(['n' => 'Ana', 'e' => 'nope']))->toHaveKey('email')
        ->and($validator->validate(['n' => '', 'e' => 'ana@x.com']))->toHaveKey('name');

    expect(Contact::count())->toBe(0);
});

it('imports in slices through Filament\'s dispatcher and records failed rows', function () {
    $rows = [['name', 'email']];
    foreach (range(1, 25) as $i) {
        $rows[] = ["Person {$i}", $i === 7 ? 'bad' : "p{$i}@x.com"];
    }
    storeCsv($rows, 'c.csv');

    $user = User::forceCreate(['name' => 'Admin']);

    $import = (new ImportStarter)->start(
        new CsvSource('imports', 'c.csv'),
        ContactImporter::class,
        'c.csv',
        ['name' => 'name', 'email' => 'email'],
        [],
        chunkSize: 10,
        user: $user,
    );

    expect($import->total_rows)->toBe(25)
        ->and($import->processed_rows)->toBe(25)
        ->and($import->successful_rows)->toBe(24)
        ->and(Contact::count())->toBe(24)
        ->and($import->failedRows()->count())->toBe(1);
});

it('converts the first sheet of an xlsx file to csv', function () {
    $path = sys_get_temp_dir().'/iw-test.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['name', 'email']));
    $writer->addRow(Row::fromValues(['Ana', 'ana@x.com']));
    $writer->addRow(Row::fromValues(['', '']));
    $writer->addRow(Row::fromValues(['Ben', 'ben@x.com']));
    $writer->close();
    Storage::disk('imports')->put('book.xlsx', file_get_contents($path));

    $csv = (new FileNormalizer)->normalize('imports', 'book.xlsx');
    $source = new CsvSource('imports', $csv);

    expect($source->getHeaders())->toBe(['name', 'email'])
        ->and($source->countRows())->toBe(2);
});
