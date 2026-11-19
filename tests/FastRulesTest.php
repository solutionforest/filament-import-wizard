<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SolutionForest\FilamentImportWizard\Support\FastRules;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\CustomValidateImporter;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\MatrixImporter;

const MATRIX_MAP = ['a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd', 'e' => 'e', 'f' => 'f', 'g' => 'g', 'h' => 'h', 'i' => 'i', 'j' => 'j', 'k' => 'k'];

function matrixValues(): array
{
    return [
        '', ' ', "\t", '0', '1', '12', ' 12', '12 ', '12.5', '-5', '1e3', '100', '101', 'abc', 'a b', 'ABCDEFGHIJ', 'ABCDEFGHIJK', str_repeat('é', 10), str_repeat('é', 11),
        'red', 'RED', 'green', 'x1', 'xray', 'p', 'q', 'z', '+1 415 555 0101', '12345', 'call me',
        'ana@x.com', 'ana.b+tag@sub.example.org', 'A@B.CO', 'bad', 'a@', '@x.com', 'a@x', 'a..b@x.com', '.a@x.com', 'a@-x.com', 'a b@x.com', 'ä@x.com', 'a@x.c', 'a@x.123', '"q"@x.com',
        str_repeat('a', 65).'@x.com', 'a@'.str_repeat('b', 64).'.com', null,
    ];
}

it('gives exactly the same errors with and without the fast path across a wide value matrix', function () {
    mt_srand(42);
    $values = matrixValues();
    $slow = new RowValidator(MatrixImporter::class, MATRIX_MAP, fast: false);
    $fast = new RowValidator(MatrixImporter::class, MATRIX_MAP, fast: true);

    $rows = [];
    for ($i = 0; $i < 6000; $i++) {
        $row = [];
        foreach (array_keys(MATRIX_MAP) as $column) {
            $row[$column] = $values[mt_rand(0, count($values) - 1)];
        }
        $rows[] = $row;
    }

    // Also rows built to pass every rule, so the fast "certainly passes" branch is exercised.
    for ($i = 0; $i < 500; $i++) {
        $rows[] = ['a' => 'abc', 'b' => '12', 'c' => 'ana@x.com', 'd' => '+1 415 555 0101', 'e' => 'red', 'f' => '3.5', 'g' => 'ok', 'h' => 'a@b.co', 'i' => 'x1', 'j' => 'p', 'k' => 'abc'];
    }

    $expected = $slow->validateMany($rows, 1);
    $actual = $fast->validateMany($rows, 1);

    expect($actual)->toBe($expected);
    expect(count(array_filter($expected, fn ($errors) => $errors === [])))->toBeGreaterThan(300);
});

it('only ever accepts e-mails that Laravel accepts (fuzz)', function () {
    mt_srand(7);
    $accepted = 0;

    $pick = fn (array $set, int $n): string => implode('', array_map(fn () => $set[mt_rand(0, count($set) - 1)], range(1, $n)));
    $alnum = str_split('abcxyzABCXYZ0123456789');

    for ($i = 0; $i < 40000; $i++) {
        $candidate = match ($i % 4) {
            // structured, mostly valid shapes with occasional separators / odd characters at the edges
            0, 1 => $pick(array_merge($alnum, ['.', '-', '_', '+']), mt_rand(1, 12)).'@'.$pick(array_merge($alnum, ['-', '.']), mt_rand(1, 10)).'.'.['com', 'io', 'c', 'co', 'x1', 'museum'][mt_rand(0, 5)],
            2 => $pick($alnum, mt_rand(1, 8)).'@'.$pick($alnum, mt_rand(1, 8)).'.'.$pick(str_split('abcdefghij'), mt_rand(2, 6)),
            default => $pick(array_merge($alnum, ['.', '-', '_', '+', '@', ' ', '"', '!', 'é']), mt_rand(1, 16)),
        };

        if (FastRules::isSafeEmail($candidate)) {
            $accepted++;
            expect(Validator::make(['e' => $candidate], ['e' => 'email:rfc'])->passes())->toBeTrue("fast path accepted [{$candidate}]");
        }
    }

    expect($accepted)->toBeGreaterThan(1000);
});

it('leaves rules it cannot reproduce to Laravel and never compiles them', function () {
    $fast = FastRules::compile([
        'a' => ['required', 'string'],
        'b' => ['required', 'regex:/^x/'],
        'c' => ['nullable', 'email:rfc,dns'],
        'd' => [Rule::unique('contacts', 'email')],
        'e' => [fn () => true],
        'f' => ['in:"a,b",c'],
        'g.*' => ['string'],
        'h' => ['nullable|string|max:5'],
    ]);

    expect(array_keys($fast->slowRules()))->toBe(['b', 'c', 'd', 'e', 'f', 'g.*', 'h'])
        ->and($fast->hasFastFields())->toBeTrue();
});

it('is bypassed when an importer customises validateData()', function () {
    $validator = new RowValidator(CustomValidateImporter::class, MATRIX_MAP);
    $row = ['a' => 'abc', 'b' => '12', 'c' => 'ana@x.com', 'd' => '', 'e' => 'red', 'f' => '3', 'g' => 'ok', 'h' => '', 'i' => 'x1', 'j' => 'p', 'k' => 'abc'];

    expect($validator->validate($row)['a'] ?? null)->toBe(['custom rule always fails']);
});
