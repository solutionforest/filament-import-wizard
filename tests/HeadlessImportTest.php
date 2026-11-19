<?php

use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use SolutionForest\FilamentImportWizard\Checks\UniqueInFile;
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;
use SolutionForest\FilamentImportWizard\Headless\ImportBlockedException;
use SolutionForest\FilamentImportWizard\Headless\ImportProgress;
use SolutionForest\FilamentImportWizard\Models\AuditStatus;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

function headlessCsv(string $name = 'api/people.csv', int $rows = 30, array $bad = [7 => 'bad'], array $duplicate = []): string
{
    $lines = ['Full Name,E-mail'];
    foreach (range(1, $rows) as $i) {
        $lines[] = "Person {$i},".($bad[$i] ?? $duplicate[$i] ?? "p{$i}@x.com");
    }
    Storage::disk('imports')->put($name, implode("\n", $lines)."\n");

    return $name;
}

it('reviews a stored file with no screen, exactly like the wizard', function () {
    $headless = HeadlessImport::for(ContactImporter::class)->file(headlessCsv(), disk: 'imports')->autoMap();

    expect($headless->headers())->toBe(['Full Name', 'E-mail'])
        ->and($headless->mapping())->toEqual(['name' => 'Full Name', 'email' => 'E-mail'])
        ->and($headless->missingRequiredColumns())->toBe([]);

    $review = $headless->review();

    expect($review->totalRows)->toBe(30)
        ->and($review->invalidRows)->toBe(1)
        ->and($review->validRows())->toBe(29)
        ->and($review->isValid())->toBeFalse()
        ->and($review->errorsByColumn)->toBe(['email' => 1])
        ->and($review->sample)->toHaveCount(1)
        ->and($review->sample[0]['row'])->toBe(7)
        ->and($review->preview)->toHaveCount(10)
        ->and($review->preview[6]['errors'])->toHaveKey('email')
        ->and($review->toArray())->toHaveKeys(['total_rows', 'valid_rows', 'invalid_rows', 'is_valid', 'errors_by_column', 'sample', 'preview', 'mapping']);
});

it('reports progress while it reviews', function () {
    $seen = [];

    HeadlessImport::for(ContactImporter::class)->file(headlessCsv(rows: 25), disk: 'imports')->autoMap()
        ->review(function (int $done, int $total) use (&$seen): void {
            $seen[] = [$done, $total];
        });

    expect($seen)->toBe([[25, 25]]);
});

it('blocks the import when rows have problems, unless asked to import the valid ones', function () {
    $user = User::forceCreate(['name' => 'Api']);
    $headless = HeadlessImport::for(ContactImporter::class)->file(headlessCsv(), disk: 'imports')->autoMap()->user($user)->chunkSize(10);

    try {
        $headless->start();
        $this->fail('expected the import to be blocked');
    } catch (ImportBlockedException $exception) {
        expect($exception->review->invalidRows)->toBe(1)->and(Contact::count())->toBe(0)->and(Import::query()->count())->toBe(0);
    }

    $import = $headless->start(allowInvalid: true);

    expect($import->total_rows)->toBe(30)
        ->and($import->successful_rows)->toBe(29)
        ->and($import->user_id)->toBe($user->getKey())
        ->and(Contact::count())->toBe(29)
        ->and($import->failedRows()->count())->toBe(1);

    $progress = ImportProgress::of($import->refresh());
    expect($progress->finished)->toBeTrue()
        ->and($progress->percent())->toBe(100.0)
        ->and($progress->toArray())->toMatchArray(['status' => 'finished', 'total_rows' => 30, 'successful_rows' => 29, 'failed_rows' => 1]);
});

it('imports a clean file straight through, reviewing first on its own', function () {
    $user = User::forceCreate(['name' => 'Api']);

    $import = HeadlessImport::for(ContactImporter::class)->file(headlessCsv(rows: 12, bad: []), disk: 'imports')->autoMap()->user($user)->start();

    expect($import->total_rows)->toBe(12)->and(Contact::count())->toBe(12);
});

it('stores an uploaded file and rejects bad types and unknown mappings', function () {
    $upload = UploadedFile::fake()->createWithContent('../../my people!.csv', "name,email\nAna,ana@x.com\n");
    $headless = HeadlessImport::for(ContactImporter::class)->upload($upload, disk: 'imports', directory: 'api-uploads');

    $files = Storage::disk('imports')->files('api-uploads');
    expect($files)->toHaveCount(1)->and($files[0])->not->toContain('..')->and($files[0])->not->toContain(' ')->and($files[0])->not->toContain('!');

    $headless->autoMap();
    expect($headless->review()->isValid())->toBeTrue();

    expect(fn () => HeadlessImport::for(ContactImporter::class)->upload(UploadedFile::fake()->createWithContent('x.php', '<?php'), disk: 'imports'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $headless->map(['nope' => 'name']))->toThrow(InvalidArgumentException::class, 'Unknown importer column')
        ->and(fn () => $headless->map(['name' => 'ghost']))->toThrow(InvalidArgumentException::class, 'no column')
        ->and(fn () => HeadlessImport::for(stdClass::class))->toThrow(InvalidArgumentException::class);
});

it('needs the required columns mapped before it reviews', function () {
    $headless = HeadlessImport::for(ContactImporter::class)->file(headlessCsv(), disk: 'imports')->map(['email' => 'E-mail']);

    expect($headless->missingRequiredColumns())->toBe(['Name']);
    expect(fn () => $headless->review())->toThrow(InvalidArgumentException::class, 'Name');
});

it('reads Excel workbooks and lets a client choose the sheet', function () {
    $path = sys_get_temp_dir().'/headless.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Notes');
    $writer->addRow(Row::fromValues(['nothing']));
    $writer->addNewSheetAndMakeItCurrent()->setName('People');
    $writer->addRow(Row::fromValues(['name', 'email']));
    $writer->addRow(Row::fromValues(['Ana', 'ana@x.com']));
    $writer->close();
    Storage::disk('imports')->put('api/book.xlsx', file_get_contents($path));

    $headless = HeadlessImport::for(ContactImporter::class)->file('api/book.xlsx', disk: 'imports');

    expect($headless->sheets())->toBe(['Notes', 'People']);

    $review = $headless->sheet(1)->autoMap()->review();
    expect($review->totalRows)->toBe(1)->and($review->isValid())->toBeTrue();
});

it('applies file-level checks and can switch the fast path off with identical results', function () {
    $name = headlessCsv(rows: 10, bad: [], duplicate: [4 => 'p1@x.com']);

    $withChecks = fn (bool $fast) => HeadlessImport::for(ContactImporter::class)->file($name, disk: 'imports')->autoMap()
        ->checks([new UniqueInFile(['email'])])->fastValidation($fast)->review();

    expect($withChecks(true)->toArray())->toBe($withChecks(false)->toArray())
        ->and($withChecks(true)->invalidRows)->toBe(1)
        ->and($withChecks(true)->sample[0]['row'])->toBe(4);
});

it('only forwards options the importer declares', function () {
    $headless = HeadlessImport::for(ContactImporter::class)->file(headlessCsv(), disk: 'imports')->options(['evil' => 1, 'overwrite' => true]);

    $options = (fn () => $this->options)->call($headless);
    expect($options)->toBe([]);
})->group('security');

it('records the run in the audit trail when asked', function () {
    $user = User::forceCreate(['name' => 'Api']);

    HeadlessImport::for(ContactImporter::class)->file(headlessCsv(rows: 5, bad: []), disk: 'imports')->autoMap()->user($user)->audit()->start();

    $audit = ImportAudit::query()->sole();

    expect($audit->status)->toBe(AuditStatus::Completed)
        ->and($audit->total_rows)->toBe(5)
        ->and($audit->user_id)->toBe($user->getKey())
        ->and($audit->checksum)->toBe(hash('sha256', Storage::disk('imports')->get('api/people.csv')))
        ->and($audit->events->pluck('to_status')->all())->toBe(['uploaded', 'reviewed', 'importing', 'completed']);
});

it('fails clearly when no file was given', function () {
    expect(fn () => HeadlessImport::for(ContactImporter::class)->headers())->toThrow(LogicException::class);
});
