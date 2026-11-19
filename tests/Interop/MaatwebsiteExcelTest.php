<?php

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use SolutionForest\FilamentImportWizard\Export\FileExport;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

require_once __DIR__.'/helpers.php';

// maatwebsite/excel (PhpSpreadsheet): optional dev dependency, installed by the CI "interop" job.
if (! class_exists(Excel::class)) {
    return;
}

beforeEach(fn () => Storage::disk('imports')->makeDirectory('interop'));

/*
 | Laravel Excel's DEFAULT value binder turns text that looks like a number ("+14155550101") into a number
 | (14155550101) when it WRITES a file — the file already lacks the "+", so no reader can recover it. These
 | tests use a StringValueBinder, which is what you should use for codes and phone numbers.
 */
class MaatExport extends StringValueBinder implements FromArray, WithCustomValueBinder, WithHeadings
{
    public function array(): array
    {
        return array_map('array_values', interopRows());
    }

    public function headings(): array
    {
        return ['name', 'email', 'phone'];
    }
}

it('imports files written by maatwebsite/excel', function (string $writer, string $extension) {
    Excel::store(new MaatExport, "interop/maat.{$extension}", 'imports', $writer);

    expect(interopImport("interop/maat.{$extension}"))->toBe(interopExpected());
})->with([['Xlsx', 'xlsx'], ['Csv', 'csv'], ['Xls', 'xls'], ['Ods', 'ods']]);

it('exports files maatwebsite/excel reads back', function (string $format) {
    foreach (interopRows() as $row) {
        Contact::create($row);
    }
    $path = FileExport::for(ContactImporter::class)->format($format)->store("interop/ours.{$format}", 'imports');

    $import = new class extends StringValueBinder implements ToArray, WithCustomValueBinder, WithHeadingRow
    {
        public function array(array $array): void {}
    };
    $rows = Excel::toArray($import, $path, 'imports')[0];

    expect(interopNormalise(array_map(fn ($row) => array_slice(array_values($row), 0, 3), $rows)))->toBe(interopNormalise(interopRows()));
})->with(['xlsx', 'csv']);
