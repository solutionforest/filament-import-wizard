<?php

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Illuminate\Support\Facades\Storage;
use SolutionForest\FilamentImportWizard\Export\FileExport;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

require_once __DIR__.'/helpers.php';

/*
 | box/spout 3 is the engine of nikazooz/laravel-simplesheet. The simplesheet package itself supports Laravel <= 8 and cannot be installed
 | next to Filament 5 / Laravel 12+, but any app that still produces files with it produces the files tested here.
 */
if (! class_exists(WriterEntityFactory::class)) {
    return;
}

beforeEach(fn () => Storage::disk('imports')->makeDirectory('interop'));

it('imports files written by box/spout 3 (the engine of nikazooz/laravel-simplesheet)', function (string $extension) {
    $path = "interop/spout3.{$extension}";
    $writer = match ($extension) {
        'xlsx' => WriterEntityFactory::createXLSXWriter(),
        'csv' => WriterEntityFactory::createCSVWriter(),
        'ods' => WriterEntityFactory::createODSWriter(),
    };
    $writer->openToFile(Storage::disk('imports')->path($path));
    $writer->addRow(WriterEntityFactory::createRowFromArray(['name', 'email', 'phone']));
    foreach (interopRows() as $row) {
        $writer->addRow(WriterEntityFactory::createRowFromArray(array_values($row)));
    }
    $writer->close();

    expect(interopImport($path))->toBe(interopExpected());
})->with(['xlsx', 'csv', 'ods']);

it('exports files box/spout 3 reads back', function (string $format) {
    foreach (interopRows() as $row) {
        Contact::create($row);
    }
    $path = FileExport::for(ContactImporter::class)->format($format)->store("interop/ours.{$format}", 'imports');
    $reader = $format === 'xlsx' ? ReaderEntityFactory::createXLSXReader() : ReaderEntityFactory::createCSVReader();
    $reader->open(Storage::disk('imports')->path($path));
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $index => $row) {
            if ($index > 1) {
                $rows[] = $row->toArray();
            }
        }
    }

    $reader->close();

    expect(interopNormalise($rows))->toBe(interopNormalise(interopRows()));
})->with(['xlsx', 'csv']);
