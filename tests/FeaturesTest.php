<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use SolutionForest\FilamentImportWizard\Checks\Stores\DatabaseSeenStore;
use SolutionForest\FilamentImportWizard\Checks\Stores\SqliteSeenStore;
use SolutionForest\FilamentImportWizard\Checks\UniqueInFile;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Rules\CommonRules;
use SolutionForest\FilamentImportWizard\Rules\DateInFormats;
use SolutionForest\FilamentImportWizard\Rules\OneOfIgnoringCase;
use SolutionForest\FilamentImportWizard\Rules\PhoneNumber;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

function passes(array $rules, mixed $value): bool
{
    return ! validator(['v' => $value], ['v' => $rules])->fails();
}

it('ships reusable rule classes', function () {
    expect(passes([new PhoneNumber], '+1 (415) 555-0101'))->toBeTrue()
        ->and(passes([new PhoneNumber], 'call me'))->toBeFalse()
        ->and(passes([new DateInFormats(['Y-m-d', 'd/m/Y'])], '31/12/2024'))->toBeTrue()
        ->and(passes([new DateInFormats(['Y-m-d'])], '31/12/2024'))->toBeFalse()
        ->and(passes([new DateInFormats(['Y-m-d'])], '2024-02-30'))->toBeFalse()
        ->and(passes([new OneOfIgnoringCase(['active', 'archived'])], ' ACTIVE '))->toBeTrue()
        ->and(passes([new OneOfIgnoringCase(['active'])], 'gone'))->toBeFalse();
});

it('offers rule sets for ImportColumn::rules()', function () {
    expect(passes(CommonRules::email(), 'a@x.com'))->toBeTrue()
        ->and(passes(CommonRules::email(), ''))->toBeFalse()
        ->and(passes(CommonRules::email(required: false), null))->toBeTrue()
        ->and(passes(CommonRules::integer(min: 1, max: 5), 7))->toBeFalse()
        ->and(passes(CommonRules::phone(required: false), null))->toBeTrue()
        ->and(passes(CommonRules::oneOf(['a', 'b']), 'B'))->toBeTrue()
        ->and(passes(CommonRules::url(), 'nope'))->toBeFalse()
        ->and(passes(CommonRules::name(max: 3), 'abcd'))->toBeFalse();
});

it('flags duplicates inside the file with constant memory state that survives between slices', function () {
    $dir = sys_get_temp_dir().'/iw-unique-'.uniqid();
    $map = ['name' => 'n', 'email' => 'e'];

    $first = new RowValidator(ContactImporter::class, $map, [], [new UniqueInFile(['email'], new SqliteSeenStore($dir))], 'run1', fresh: true);
    expect($first->validate(['n' => 'A', 'e' => 'a@x.com'], 1))->toBe([]);

    // A later request resumes the same run with new check instances.
    $second = new RowValidator(ContactImporter::class, $map, [], [new UniqueInFile(['email'], new SqliteSeenStore($dir))], 'run1', fresh: false);
    expect($second->validate(['n' => 'B', 'e' => 'A@X.com '], 2))->toHaveKey('email')
        ->and($second->validate(['n' => 'C', 'e' => 'c@x.com'], 3))->toBe([]);

    $second->finish('run1');
    expect(glob($dir.'/*.sqlite'))->toBe([]);
});

it('converts non-UTF-8 csv files and keeps UTF-8 ones untouched', function () {
    Storage::disk('imports')->put('latin.csv', mb_convert_encoding("name,city\nZoë,Zürich\n", 'Windows-1252', 'UTF-8'));
    Storage::disk('imports')->put('utf8.csv', "name,city\nZoë,Zürich\n");
    Storage::disk('imports')->put('utf16.csv', mb_convert_encoding("name,city\nZoë,Zürich\n", 'UTF-16LE', 'UTF-8'));

    $normalizer = new FileNormalizer;

    expect($normalizer->normalize('imports', 'utf8.csv'))->toBe('utf8.csv');

    foreach (['latin.csv', 'utf16.csv'] as $file) {
        $csv = $normalizer->normalize('imports', $file);
        $rows = iterator_to_array((new CsvSource('imports', $csv))->read(['name', 'city'], (new CsvSource('imports', $csv))->getDataStartOffset(), 5));

        expect($csv)->not->toBe($file)
            ->and($rows[0])->toBe(['name' => 'Zoë', 'city' => 'Zürich']);
    }
});

it('lists sheets and reads the chosen one from xlsx and legacy xls', function () {
    $path = sys_get_temp_dir().'/iw-multi.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('First');
    $writer->addRow(Row::fromValues(['a']));
    $writer->addNewSheetAndMakeItCurrent()->setName('Second');
    $writer->addRow(Row::fromValues(['name', 'email']));
    $writer->addRow(Row::fromValues(['Ana', 'ana@x.com']));
    $writer->close();
    Storage::disk('imports')->put('multi.xlsx', file_get_contents($path));

    $normalizer = new FileNormalizer;
    expect($normalizer->sheets('imports', 'multi.xlsx'))->toBe(['First', 'Second']);

    $csv = $normalizer->normalize('imports', 'multi.xlsx', 1);
    expect((new CsvSource('imports', $csv))->getHeaders())->toBe(['name', 'email']);

    $xls = sys_get_temp_dir().'/iw-legacy.xls';
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([['name', 'email'], ['Ben', 'ben@x.com']]);
    (new Xls($book))->save($xls);
    Storage::disk('imports')->put('legacy.xls', file_get_contents($xls));

    $csv = $normalizer->normalize('imports', 'legacy.xls');
    expect((new CsvSource('imports', $csv))->getHeaders())->toBe(['name', 'email'])
        ->and((new CsvSource('imports', $csv))->countRows())->toBe(1);
});

it('lets each panel configure its own disk, directory and limits', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = ImportWizardPlugin::current();
    $adminDirectory = $admin->getDirectory();

    Filament::setCurrentPanel(Filament::getPanel('staff'));
    $staff = ImportWizardPlugin::current();

    expect($admin->getChunkSize())->toBe(500)
        ->and($adminDirectory)->toBe('import-wizard/admin')
        ->and($staff->getChunkSize())->toBe(7)
        ->and($staff->getDisk())->toBe('imports')
        ->and($staff->getDirectory())->toBe('staff-imports/staff')
        ->and($staff->getMaxCollectedErrors())->toBe(2)
        ->and(Filament::getPanel('staff')->getPages())->toContain(ImportContacts::class);
});

it('keeps uploads per panel: a path from another panel is rejected', function () {
    $this->actingAs(User::forceCreate(['name' => 'Admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::disk('imports')->put('staff-imports/staff/other.csv', "name,email\nA,a@x.com\n");

    Livewire::test(ImportContacts::class)
        ->set('data.file', ['uuid' => 'staff-imports/staff/other.csv'])
        ->call('analyzeUpload')
        ->assertSet('step', 1);
});

it('runs a server-side import from the console, validating first', function () {
    $user = User::forceCreate(['name' => 'Admin']);
    Storage::disk('imports')->put('big.csv', "name,email\nAna,ana@x.com\nBen,bad\nCy,cy@x.com\n");

    $this->artisan('import-wizard:run', ['importer' => ContactImporter::class, 'path' => 'big.csv', '--disk' => 'imports', '--user' => $user->getKey()])
        ->assertFailed();
    expect(Contact::count())->toBe(0);

    $this->artisan('import-wizard:run', ['importer' => ContactImporter::class, 'path' => 'big.csv', '--disk' => 'imports', '--dry-run' => true])
        ->assertFailed();

    $this->artisan('import-wizard:run', ['importer' => ContactImporter::class, 'path' => 'big.csv', '--disk' => 'imports', '--user' => $user->getKey(), '--force' => true])
        ->assertSuccessful();
    expect(Contact::count())->toBe(2);
});

it('can keep the duplicate-check state in the shared database for multi-server setups', function () {
    $map = ['name' => 'n', 'email' => 'e'];

    $first = new RowValidator(ContactImporter::class, $map, [], [new UniqueInFile(['email'], new DatabaseSeenStore)], 'run-db', fresh: true);
    expect($first->validate(['n' => 'A', 'e' => 'a@x.com'], 1))->toBe([]);

    // Another server picks up the next slice.
    $other = new RowValidator(ContactImporter::class, $map, [], [new UniqueInFile(['email'], new DatabaseSeenStore)], 'run-db', fresh: false);
    expect($other->validate(['n' => 'B', 'e' => 'a@x.com'], 2))->toHaveKey('email');

    $other->finish('run-db');
    expect(DB::table('import_wizard_seen')->count())->toBe(0);
});

it('checks a whole slice at once with the same result as row by row, on both stores', function (string $store) {
    $dir = sys_get_temp_dir().'/iw-batch-'.uniqid();
    $make = fn () => $store === 'db' ? new DatabaseSeenStore : new SqliteSeenStore($dir);
    $rows = [['email' => 'a@x.com'], ['email' => 'b@x.com'], ['email' => 'A@x.com '], ['email' => ''], ['email' => 'b@x.com'], ['email' => 'c@x.com']];

    $batch = new UniqueInFile(['email'], $make());
    $batch->begin('batch-'.$store, true);
    $many = $batch->checkMany($rows, 1);

    $single = new UniqueInFile(['email'], $make());
    $single->begin('single-'.$store, true);
    $one = array_map(fn ($row, $i) => $single->check($row, $i + 1), $rows, array_keys($rows));

    expect(array_map(fn ($errors) => $errors === [], $many))->toBe([true, true, false, true, false, true])
        ->and(array_map(fn ($errors) => array_keys($errors), $many))->toBe(array_map(fn ($errors) => array_keys($errors), $one));

    // A second slice of the same run sees the first slice's keys.
    $next = new UniqueInFile(['email'], $make());
    $next->begin('batch-'.$store, false);
    expect($next->checkMany([['email' => 'c@x.com'], ['email' => 'd@x.com']], 7)[0])->toHaveKey('email');

    $batch->finish('batch-'.$store);
    $single->finish('single-'.$store);
})->with(['sqlite', 'db']);
