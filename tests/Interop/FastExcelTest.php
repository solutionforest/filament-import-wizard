<?php

use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use SolutionForest\FilamentImportWizard\Export\FileExport;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

require_once __DIR__.'/helpers.php';

// rap2hpoutre/fast-excel (OpenSpout): optional dev dependency, installed by the CI "interop" job.
if (! class_exists(FastExcel::class)) {
    return;
}

beforeEach(fn () => Storage::disk('imports')->makeDirectory('interop'));

it('imports files written by rap2hpoutre/fast-excel', function (string $extension) {
    (new FastExcel(collect(interopRows())))->export(Storage::disk('imports')->path("interop/fast.{$extension}"));

    expect(interopImport("interop/fast.{$extension}"))->toBe(interopExpected());
})->with(['xlsx', 'csv', 'ods']);

it('exports files rap2hpoutre/fast-excel reads back', function (string $format) {
    foreach (interopRows() as $row) {
        Contact::create($row);
    }
    $path = FileExport::for(ContactImporter::class)->format($format)->store("interop/ours.{$format}", 'imports');
    $rows = (new FastExcel)->import(Storage::disk('imports')->path($path))->map(fn ($row) => array_values($row))->all();

    expect(interopNormalise($rows))->toBe(interopNormalise(interopRows()));
})->with(['xlsx', 'csv']);
