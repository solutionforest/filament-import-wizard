<?php

use Illuminate\Support\Facades\Storage;
use SolutionForest\FilamentImportWizard\Export\FileExport;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

require_once __DIR__.'/helpers.php';

// spatie/simple-excel (OpenSpout): optional dev dependency, installed by the CI "interop" job.
if (! class_exists(SimpleExcelWriter::class)) {
    return;
}

beforeEach(fn () => Storage::disk('imports')->makeDirectory('interop'));

it('imports files written by spatie/simple-excel', function (string $extension) {
    $writer = SimpleExcelWriter::create(Storage::disk('imports')->path("interop/spatie.{$extension}"));
    $writer->addRows(interopRows());
    $writer->close();

    expect(interopImport("interop/spatie.{$extension}"))->toBe(interopExpected());
})->with(['xlsx', 'csv', 'ods']);

it('imports real numeric cells written by spatie/simple-excel', function () {
    $writer = SimpleExcelWriter::create(Storage::disk('imports')->path('interop/typed.xlsx'));
    $writer->addRow(['name' => 'Typed', 'email' => 'typed@x.com', 'phone' => 4155550101]);
    $writer->close();

    expect(interopImport('interop/typed.xlsx'))->toBe([['name' => 'Typed', 'email' => 'typed@x.com', 'phone' => '4155550101']]);
});

it('exports files spatie/simple-excel reads back', function (string $format) {
    foreach (interopRows() as $row) {
        Contact::create($row);
    }
    $path = FileExport::for(ContactImporter::class)->format($format)->store("interop/ours.{$format}", 'imports');
    $rows = SimpleExcelReader::create(Storage::disk('imports')->path($path))->getRows()->map(fn ($row) => array_values($row))->all();

    expect(interopNormalise($rows))->toBe(interopNormalise(interopRows()));
})->with(['xlsx', 'csv']);
