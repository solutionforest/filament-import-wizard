<?php

use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use SolutionForest\FilamentImportWizard\Checks\Stores\SqliteSeenStore;
use SolutionForest\FilamentImportWizard\Checks\UniqueInFile;
use SolutionForest\FilamentImportWizard\Export\FileExport;
use SolutionForest\FilamentImportWizard\Headless\HeadlessImport;
use SolutionForest\FilamentImportWizard\Support\ChunkedUpload;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;

/*
 | Stress tests: `composer stress` (or `vendor/bin/pest --group=stress`). Excluded from the normal run.
 | ROWS defaults to 50,000; override with IMPORT_WIZARD_STRESS_ROWS. Each test prints its timing and
 | peak-memory growth and asserts memory stays flat — the point of the streaming design.
 */

const STRESS_INVALID_EVERY = 100;   // 1 % of rows have a malformed e-mail
const STRESS_DUPLICATE_EVERY = 250; // 0.4 % repeat an earlier e-mail

function stressRows(): int
{
    return (int) (getenv('IMPORT_WIZARD_STRESS_ROWS') ?: 50000);
}

function stressCsv(string $name = 'stress/people.csv'): string
{
    $path = sys_get_temp_dir().'/'.str_replace('/', '-', $name);
    $handle = fopen($path, 'w');
    fputcsv($handle, ['name', 'email', 'phone'], ',', '"', '');

    for ($i = 1; $i <= stressRows(); $i++) {
        $email = $i % STRESS_INVALID_EVERY === 0 ? "bad-{$i}" : ($i % STRESS_DUPLICATE_EVERY === 0 ? 'p1@example.com' : "p{$i}@example.com");
        fputcsv($handle, ["Person {$i}", $email, '+1 415 555 '.str_pad((string) ($i % 10000), 4, '0', STR_PAD_LEFT)], ',', '"', '');
    }

    fclose($handle);
    Storage::disk('imports')->put($name, fopen($path, 'r'));
    @unlink($path);

    return $name;
}

/**
 * @return array{0: mixed, 1: float, 2: float} result, seconds, peak memory growth in MB
 */
function stressMeasure(callable $work): array
{
    gc_collect_cycles();
    $baseline = memory_get_usage();
    $peakBefore = memory_get_peak_usage();
    $start = microtime(true);

    $result = $work();

    $seconds = microtime(true) - $start;
    $growth = max(0, memory_get_peak_usage() - max($baseline, $peakBefore)) / 1048576;

    return [$result, $seconds, $growth];
}

function stressReport(string $label, float $seconds, float $mb, int $rows): void
{
    fwrite(STDERR, sprintf("\n  [stress] %-34s %8.2fs  %7.1f MB peak growth  %9s rows/s\n", $label, $seconds, $mb, number_format($rows / max($seconds, 0.001))));
}

function expectedInvalid(): int
{
    $rows = stressRows();
    $invalid = intdiv($rows, STRESS_INVALID_EVERY);
    // Duplicates that are not also invalid rows (multiples of both 100 and 250 are already invalid).
    $duplicates = intdiv($rows, STRESS_DUPLICATE_EVERY) - intdiv($rows, 500);

    return $invalid + $duplicates;
}

it('counts and slices a large CSV without holding it in memory', function () {
    $name = stressCsv();
    $source = new CsvSource('imports', $name);

    [$total, $seconds, $mb] = stressMeasure(fn () => $source->countRows());
    stressReport('count rows', $seconds, $mb, stressRows());

    expect($total)->toBe(stressRows())->and($mb)->toBeLessThan(32);
})->group('stress');

it('reviews every row (rules + duplicate check) in flat memory', function () {
    $name = stressCsv();
    $source = new CsvSource('imports', $name);
    $headers = $source->getHeaders();
    $validator = new RowValidator(ContactImporter::class, ['name' => 'name', 'email' => 'email', 'phone' => 'phone'], [], [new UniqueInFile(['email'], new SqliteSeenStore)], 'stress', true);

    [$invalid, $seconds, $mb] = stressMeasure(function () use ($source, $headers, $validator): int {
        $offset = $source->getDataStartOffset();
        $row = 0;
        $invalid = 0;

        do {
            $generator = $source->read($headers, $offset, 2000);
            $rows = iterator_to_array($generator, false);

            foreach ($validator->validateMany($rows, $row + 1) as $errors) {
                $row++;
                $errors === [] || $invalid++;
            }

            $offset = $generator->getReturn();
        } while (count($rows) === 2000);

        return $invalid;
    });

    $validator->finish('stress');
    stressReport('review (validate + unique)', $seconds, $mb, stressRows());

    expect($invalid)->toBe(expectedInvalid())->and($mb)->toBeLessThan(48);
})->group('stress');

it('imports every valid row through sliced queue jobs in flat memory', function () {
    $name = stressCsv();
    $user = User::forceCreate(['name' => 'Stress']);

    [$import, $seconds, $mb] = stressMeasure(fn (): Import => (new ImportStarter)->start(
        new CsvSource('imports', $name),
        ContactImporter::class,
        'people.csv',
        ['name' => 'name', 'email' => 'email', 'phone' => 'phone'],
        [],
        chunkSize: 500,
        user: $user,
    ));

    stressReport('import (sync queue, 500/slice)', $seconds, $mb, stressRows());

    // Only the importer's own validation fails here (invalid e-mails); duplicates are updated by the importer? No: ContactImporter always creates.
    expect($import->total_rows)->toBe(stressRows())
        ->and($import->processed_rows)->toBe(stressRows())
        ->and($import->failedRows()->count())->toBe(intdiv(stressRows(), STRESS_INVALID_EVERY))
        ->and(Contact::count())->toBe(stressRows() - intdiv(stressRows(), STRESS_INVALID_EVERY))
        ->and($mb)->toBeLessThan(96);
})->group('stress');

it('converts a large XLSX to CSV in flat memory', function () {
    $path = sys_get_temp_dir().'/stress.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['name', 'email', 'phone']));

    for ($i = 1; $i <= stressRows(); $i++) {
        $writer->addRow(Row::fromValues(["Person {$i}", "p{$i}@example.com", '+1 415 555 0101']));
    }

    $writer->close();
    Storage::disk('imports')->put('stress/book.xlsx', fopen($path, 'r'));
    @unlink($path);

    [$csv, $seconds, $mb] = stressMeasure(fn () => (new FileNormalizer)->normalize('imports', 'stress/book.xlsx'));
    stressReport('xlsx → csv conversion', $seconds, $mb, stressRows());

    expect((new CsvSource('imports', $csv))->countRows())->toBe(stressRows())->and($mb)->toBeLessThan(48);
})->group('stress');

it('joins a large chunked upload without loading it', function () {
    $name = stressCsv();
    $content = Storage::disk('imports')->get($name);
    $uploads = new ChunkedUpload('imports', 'stress-chunks');

    [$path, $seconds, $mb] = stressMeasure(function () use ($uploads, $content): string {
        $begin = $uploads->begin('people.csv', strlen($content), 'u1');

        foreach (str_split($content, $begin['chunkSize']) as $index => $part) {
            $uploads->put($begin['id'], $index, UploadedFile::fake()->createWithContent('chunk', $part), 'u1');
        }

        return $uploads->complete($begin['id'], 'u1');
    });

    stressReport(sprintf('chunked upload (%.1f MB)', strlen($content) / 1048576), $seconds, $mb, stressRows());

    expect(Storage::disk('imports')->size($path))->toBe(strlen($content));
})->group('stress');

it('exports and re-imports a large table in flat memory, round-tripping every row', function () {
    $now = now()->toDateTimeString();
    foreach (array_chunk(range(1, stressRows()), 1000) as $chunk) {
        Contact::query()->insert(array_map(fn (int $i): array => ['name' => "Person {$i}", 'email' => "p{$i}@example.com", 'phone' => '+1 415 555 '.str_pad((string) ($i % 10000), 4, '0', STR_PAD_LEFT), 'created_at' => $now, 'updated_at' => $now], $chunk));
    }

    [$path, $seconds, $mb] = stressMeasure(fn (): string => FileExport::for(ContactImporter::class)->format('xlsx')->store('stress/export.xlsx', 'imports'));
    stressReport('export to xlsx', $seconds, $mb, stressRows());
    expect($mb)->toBeLessThan(48);

    [$review, $seconds, $mb] = stressMeasure(fn () => HeadlessImport::for(ContactImporter::class)->file($path, 'imports')->autoMap()->review());
    stressReport('re-review the exported xlsx', $seconds, $mb, stressRows());

    expect($review->totalRows)->toBe(stressRows())->and($review->isValid())->toBeTrue()->and($mb)->toBeLessThan(48);
})->group('stress');
