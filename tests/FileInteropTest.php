<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

/*
 | Files written by other spreadsheet writers. The fixtures were produced with XlsxWriter, the library the
 | `xlswriter` PHP extension (libxlsxwriter) is a port of — in its constant-memory "fast mode" and with typed cells.
 */

it('imports a workbook written in xlswriter-style constant-memory mode', function () {
    Storage::disk('imports')->put('interop/fast.xlsx', file_get_contents(__DIR__.'/Fixtures/files/xlsxwriter-fast.xlsx'));

    $headless = HeadlessImport::for(ContactImporter::class)->file('interop/fast.xlsx', 'imports')->autoMap();
    $review = $headless->review();

    expect($review->isValid())->toBeTrue()->and($review->totalRows)->toBe(4);

    $headless->user(User::forceCreate(['name' => 'Api']))->start();

    expect(Contact::query()->orderBy('id')->get(['name', 'email', 'phone'])->map->toArray()->all())->toBe([
        ['name' => 'Ana, "the boss"', 'email' => 'ana@x.com', 'phone' => '0071234567'],
        ['name' => "Line one\nLine two", 'email' => 'ben@x.com', 'phone' => '+14155550101'],
        ['name' => 'Zoë Müller 日本語 😀', 'email' => 'zoe@x.com', 'phone' => '(415) 555-0101'],
        ['name' => 'Plain', 'email' => 'plain@x.com', 'phone' => null],
    ]);
});

it('reads typed cells: numbers, dates, booleans and the cached result of formulas', function () {
    Storage::disk('imports')->put('interop/typed.xlsx', file_get_contents(__DIR__.'/Fixtures/files/xlsxwriter-typed.xlsx'));

    $csv = (new FileNormalizer)->normalize('imports', 'interop/typed.xlsx');
    $source = new CsvSource('imports', $csv);
    $rows = iterator_to_array($source->read($source->getHeaders(), $source->getDataStartOffset(), 5), false);

    expect($rows)->toBe([[
        'name' => 'Typed',
        'email' => 'typed@x.com',
        'phone' => '4155550101',
        'age' => '42',
        'active' => '1',
        'joined' => '2024-03-05 10:20:30',
        'score' => '84', // the formula's cached result, not "=D2*2"
    ]]);
});

it('reads legacy .xls cells the same way: dates as dates, booleans as 1/0, formulas as their result', function () {
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->fromArray([['name', 'joined', 'n', 'flag', 'f', 'code'], ['A', null, 12.5, true, null, '007']]);
    $sheet->setCellValue('B2', Date::PHPToExcel(new DateTime('2024-03-05 10:20:30')));
    $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
    $sheet->setCellValue('E2', '=C2*2');
    $sheet->setCellValueExplicit('F2', '007', DataType::TYPE_STRING);
    $path = sys_get_temp_dir().'/legacy.xls';
    (new Xls($book))->save($path);
    Storage::disk('imports')->put('interop/legacy.xls', file_get_contents($path));

    $csv = (new FileNormalizer)->normalize('imports', 'interop/legacy.xls');
    $source = new CsvSource('imports', $csv);

    expect(iterator_to_array($source->read($source->getHeaders(), $source->getDataStartOffset(), 3), false))->toBe([
        ['name' => 'A', 'joined' => '2024-03-05 10:20:30', 'n' => '12.5', 'flag' => '1', 'f' => '25', 'code' => '007'],
    ]);
});
