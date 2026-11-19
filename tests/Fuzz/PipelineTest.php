<?php

require_once __DIR__.'/Support.php';

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use SolutionForest\FilamentImportWizard\Rules\OneOfIgnoringCase;
use SolutionForest\FilamentImportWizard\Rules\PhoneNumber;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\DynamicImporter;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\PlainContactImporter;

/**
 * @param  list<string>  $columns
 * @return array<string, array{rules: array<mixed>, cast: string|null, label: string}>
 */
function randomDefinition(array $columns): array
{
    $definition = [];

    $satisfiable = [['required', 'string', 'max:10'], ['nullable', 'integer', 'min:1', 'max:100'], ['required', 'in:red,green'], ['required', 'email'], ['nullable', new PhoneNumber], ['required', 'numeric', 'min:0']];

    foreach ($columns as $i => $column) {
        $definition[$column] = [
            // The first column always has rules that ordinary values satisfy, so fully valid rows occur.
            'rules' => $i === 0 ? $satisfiable[mt_rand(0, count($satisfiable) - 1)] : randomRules(),
            'cast' => [null, null, null, 'integer', 'numeric', 'boolean'][mt_rand(0, 5)],
            'label' => strtoupper($column).' field',
        ];
    }

    return $definition;
}

it('gives identical errors and messages with and without the fast path, whole rows with casts and custom attribute names', function (int $seed) {
    mt_srand($seed);
    $wild = array_values(array_filter(valuePool(), 'is_scalar')); // CSV cells are scalars
    $friendly = ['abc', 'ab', '12', '5', '2', 'red', 'a@x.com', 'ana@x.com', '+1 415 555 0101', 'p', 'x1', '1', '0', '2024-01-31'];
    $pool = [...$wild, ...array_merge(...array_fill(0, 12, $friendly))];
    $columns = ['a', 'b', 'c'];
    $mismatches = [];
    $accepted = 0;

    for ($definitionIndex = 0; $definitionIndex < 40; $definitionIndex++) {
        DynamicImporter::$definition = randomDefinition($columns);
        $map = array_combine($columns, $columns);

        $slow = new RowValidator(DynamicImporter::class, $map, fast: false);
        $fast = new RowValidator(DynamicImporter::class, $map, fast: true);

        $rows = [];
        for ($r = 0; $r < 120; $r++) {
            $row = [];
            foreach ($columns as $column) {
                $row[$column] = $pool[mt_rand(0, count($pool) - 1)];
            }
            $rows[] = $row;
        }

        $expected = $slow->validateMany($rows, 1);
        $actual = $fast->validateMany($rows, 1);
        $accepted += count(array_filter($expected, fn ($errors) => $errors === []));

        foreach ($rows as $index => $row) {
            if ($expected[$index] !== $actual[$index]) {
                $mismatches[] = json_encode(['rules' => array_map(fn ($d) => describeRules($d['rules']).' cast='.($d['cast'] ?? '-'), DynamicImporter::$definition), 'row' => $row, 'laravel' => $expected[$index], 'fast' => $actual[$index]], JSON_PARTIAL_OUTPUT_ON_ERROR);

                if (count($mismatches) >= 3) {
                    break 2;
                }
            }
        }
    }

    expect($mismatches)->toBe([]);
})->with([1, 2, 3, 4]);

it('exhaustively agrees with Laravel for every single rule x every value, and every pair of rules x every value', function () {
    $values = valuePool();
    $rules = [
        'required', 'nullable', 'string', 'integer', 'numeric', 'bail', 'sometimes', 'min:0', 'min:2', 'min:5', 'max:0', 'max:3', 'max:10', 'email', 'email:rfc', 'in:red,green', 'in:1,2,3', 'in:0,1', 'in:a',
        new PhoneNumber, new OneOfIgnoringCase(['red']), new FailsOnBoom,
        'regex:/^x/', 'alpha', 'boolean', 'size:3', 'between:1,5', 'digits:3', 'email:dns',
    ];

    $mismatches = [];
    $checked = 0;

    foreach ($rules as $first) {
        foreach ([null, ...$rules] as $second) {
            $set = $second === null ? [$first] : [$first, $second];

            // Singles meet every value; pairs meet every 6th (every 3rd in `composer fuzz`) to keep the default run quick.
            foreach ($second === null ? $values : array_filter($values, fn ($_, $i) => $i % (fuzzCases() >= 60000 ? 3 : 6) === 0, ARRAY_FILTER_USE_BOTH) as $value) {
                $checked++;

                if (combinedVerdict($set, ['f' => $value]) !== laravelVerdict($set, ['f' => $value])) {
                    $mismatches[] = describeRules($set).' value='.describeValue($value);

                    if (count($mismatches) >= 6) {
                        break 3;
                    }
                }
            }
        }
    }

    expect($mismatches)->toBe([]);
    expect($checked)->toBeGreaterThan(25000);
});

it('imports the same rows and records the same failures with and without ValidatesQuickly', function () {
    $user = User::forceCreate(['name' => 'Fuzz']);
    mt_srand(77);

    $columns = ['name', 'email', 'phone'];
    $rows = ['name,email,phone'];
    $pools = [
        ['Ana', 'B', 'Ana', 'é', "O'Neil", 'a,b', '', ' ', str_repeat('x', 60)],
        ['ben@x.com', 'ana@x.com', 'ben@x.com', 'bad', 'a@x', '', 'x@y.museum'],
        ['+1 415 555 0101', '+14155550101', '+1 415 555 0101', 'call', '12345', '', '(415) 555-0101'],
    ];
    for ($i = 0; $i < 800; $i++) {
        $rows[] = implode(',', array_map(fn ($pool) => '"'.str_replace('"', '""', $pool[mt_rand(0, count($pool) - 1)]).'"', $pools));
    }
    Storage::disk('imports')->put('fuzz/people.csv', implode("\n", $rows)."\n");

    $run = function (string $importer) use ($user): array {
        Contact::query()->delete();
        $import = (new ImportStarter)->start(new CsvSource('imports', 'fuzz/people.csv'), $importer, 'people.csv', ['name' => 'name', 'email' => 'email', 'phone' => 'phone'], [], 100, $user);

        return [
            'ok' => $import->successful_rows,
            'failed' => $import->failedRows()->orderBy('id')->get()->map(fn ($row) => [$row->data, $row->validation_error])->all(),
            'rows' => Contact::query()->orderBy('id')->get(['name', 'email', 'phone'])->map->toArray()->all(),
        ];
    };

    $plain = $run(PlainContactImporter::class);
    $quick = $run(ContactImporter::class);

    expect($quick)->toBe($plain);
    expect($plain['ok'])->toBeGreaterThan(100)->and(count($plain['failed']))->toBeGreaterThan(30);
});

it('exercises the fast "certainly passes" branch end to end on mostly-valid data, equal to Laravel', function () {
    mt_srand(5);
    $satisfiable = [['required', 'string', 'max:10'], ['nullable', 'integer', 'min:1', 'max:100'], ['required', 'in:red,green'], ['required', 'email'], ['nullable', new PhoneNumber], ['required', 'numeric', 'min:0'], ['nullable', 'string', 'min:2']];
    $good = ['abc', 'ab', '12', '5', 'red', 'green', 'a@x.com', 'ana@x.com', '+1 415 555 0101', '3.5', ''];
    $noise = valuePool();
    $columns = ['a', 'b', 'c', 'd'];
    $accepted = 0;
    $total = 0;

    for ($d = 0; $d < 30; $d++) {
        DynamicImporter::$definition = array_map(fn () => ['rules' => $satisfiable[mt_rand(0, count($satisfiable) - 1)], 'cast' => null], array_combine($columns, $columns));
        $map = array_combine($columns, $columns);

        $rows = [];
        for ($r = 0; $r < 150; $r++) {
            $rows[] = array_map(fn () => mt_rand(0, 9) === 0 ? (is_scalar($v = $noise[mt_rand(0, count($noise) - 1)]) ? $v : 'x') : $good[mt_rand(0, count($good) - 1)], $map);
        }

        $expected = (new RowValidator(DynamicImporter::class, $map, fast: false))->validateMany($rows, 1);
        $actual = (new RowValidator(DynamicImporter::class, $map, fast: true))->validateMany($rows, 1);

        expect($actual)->toBe($expected);
        $accepted += count(array_filter($expected, fn ($errors) => $errors === []));
        $total += count($rows);
    }

    expect($accepted)->toBeGreaterThan(40)->and($accepted)->toBeLessThan($total);
});
