<?php

require_once __DIR__.'/Support.php';

use SolutionForest\FilamentImportWizard\Support\FastRules;

it('never accepts what Laravel rejects: random rule sets x a large pool of values', function (int $seed) {
    mt_srand($seed);
    $pool = valuePool();
    $mismatches = [];
    $fastAccepted = 0;
    $cases = intdiv(fuzzCases(), 3);

    for ($i = 0; $i < $cases; $i++) {
        $rules = randomRules();
        $value = $pool[mt_rand(0, count($pool) - 1)];
        $data = ['f' => $value, 'other' => ['x', 'y', null][mt_rand(0, 2)]];

        $fast = FastRules::compile(['f' => $rules]);
        $accepts = $fast->passes($data);
        $accepts && $fastAccepted++;

        $combined = combinedVerdict($rules, $data);
        $laravel = laravelVerdict($rules, $data);

        if ($combined !== $laravel) {
            $mismatches[] = describeRules($rules).' value='.describeValue($value)." other={$data['other']} laravel={$laravel} combined={$combined}";

            if (count($mismatches) >= 8) {
                break;
            }
        }
    }

    expect($mismatches)->toBe([], 'fast path diverged from Laravel');
    expect($fastAccepted)->toBeGreaterThan(50); // the fast branch really is exercised
})->with([101, 202, 303]);

it('agrees with Laravel on generated strings of every shape (numbers, whitespace, unicode, lengths)', function () {
    mt_srand(9);
    $mismatches = [];
    $ruleSets = [
        ['required', 'string', 'max:10'], ['nullable', 'integer', 'min:1', 'max:100'], ['required', 'numeric', 'min:0'], ['nullable', 'numeric', 'max:5'],
        ['string', 'min:2'], ['nullable', 'string', 'max:3'], ['required', 'in:1,2,3'], ['nullable', 'in:a,b'], ['required', 'email'], ['nullable', 'email:rfc', 'max:20'],
        ['required', 'integer'], ['integer', 'numeric'], ['numeric', 'max:10', 'min:2'], ['string', 'integer', 'max:2'],
    ];

    $glyphs = ['0', '1', '2', '5', '9', '-', '+', '.', 'e', 'E', ' ', "\t", "\n", 'a', 'Z', 'é', '日', '😀', ',', '_', 'x', '@'];
    $cases = intdiv(fuzzCases(), 2);

    for ($i = 0; $i < $cases; $i++) {
        $value = '';
        for ($n = mt_rand(0, 8); $n > 0; $n--) {
            $value .= $glyphs[mt_rand(0, count($glyphs) - 1)];
        }

        $rules = $ruleSets[mt_rand(0, count($ruleSets) - 1)];

        if (combinedVerdict($rules, ['f' => $value]) !== laravelVerdict($rules, ['f' => $value])) {
            $mismatches[] = describeRules($rules).' value='.describeValue($value);

            if (count($mismatches) >= 8) {
                break;
            }
        }
    }

    expect($mismatches)->toBe([]);
});

it('agrees with Laravel on generated numbers of every type and magnitude', function () {
    mt_srand(11);
    $mismatches = [];
    $ruleSets = [['integer'], ['numeric'], ['integer', 'min:1', 'max:100'], ['numeric', 'min:0', 'max:1'], ['required', 'integer', 'max:5'], ['string'], ['max:3'], ['min:2', 'max:4'], ['in:1,2,3'], ['in:1.5,0']];

    for ($i = 0; $i < intdiv(fuzzCases(), 4); $i++) {
        $value = match (mt_rand(0, 6)) {
            0 => mt_rand(-200, 200),
            1 => mt_rand(-200, 200) / 10,
            2 => (string) mt_rand(-200, 200),
            3 => number_format(mt_rand(-2000, 2000) / 10, mt_rand(0, 3), '.', ''),
            4 => [true, false, 0, 1, 0.0, PHP_INT_MAX, PHP_INT_MIN, 1e15, 1e-9][mt_rand(0, 8)],
            5 => mt_rand(0, 1) ? str_repeat((string) mt_rand(1, 9), mt_rand(15, 25)) : (string) mt_rand(),
            default => sprintf('%de%d', mt_rand(1, 9), mt_rand(-3, 3)),
        };

        $rules = $ruleSets[mt_rand(0, count($ruleSets) - 1)];

        if (combinedVerdict($rules, ['f' => $value]) !== laravelVerdict($rules, ['f' => $value])) {
            $mismatches[] = describeRules($rules).' value='.describeValue($value);

            if (count($mismatches) >= 8) {
                break;
            }
        }
    }

    expect($mismatches)->toBe([]);
});

it('keeps the verdict for fields that depend on other fields', function () {
    $cases = [
        [['same:other'], ['f' => 'a', 'other' => 'a']], [['same:other'], ['f' => 'a', 'other' => 'b']],
        [['required_with:other'], ['f' => '', 'other' => 'x']], [['required_with:other'], ['f' => '', 'other' => null]],
        [['required_if:other,x'], ['f' => '', 'other' => 'x']], [['different:other', 'string'], ['f' => 'a', 'other' => 'a']],
        [['string', new NeedsData], ['f' => 'a', 'other' => 'x']], [['string', new NeedsData], ['f' => 'a', 'other' => 'y']],
        [['confirmed'], ['f' => 'a', 'f_confirmation' => 'a']], [['gt:other'], ['f' => 5, 'other' => 3]], [['gt:other'], ['f' => 1, 'other' => 3]],
    ];

    foreach ($cases as [$rules, $data]) {
        expect(combinedVerdict($rules, $data))->toBe(laravelVerdict($rules, $data), describeRules($rules).' '.json_encode($data));
    }
});

it('agrees on every numeric-looking string for numeric / integer with min and max', function () {
    $shapes = [];
    foreach (['', ' ', '+', '-', '.', 'e'] as $prefix) {
        foreach (['0', '1', '2', '3', '4', '5', '10', '0.5', '1.5', '2.9', '3.0', '3.1', '00', '007', '1e0', '1e1', '1e-1', '1E2', '5e-1', '0e5', '.5', '5.', '1_0', '0x1', 'NaN', 'INF'] as $number) {
            foreach (['', ' ', "\t", "\n", 'x'] as $suffix) {
                $shapes[] = $prefix.$number.$suffix;
            }
        }
    }
    $shapes = array_values(array_unique([...$shapes, ' 1', '1 ', ' 1 ', "\n1", "1\n", '0.0000001', '3.0000000000000001', '2.9999999999999999', '3.00000000000001', '2.99999999999999', '3.000000000000000', '0.99999999999999999', '1.0000000000000001', '1.00000000000001', '123456789012345', '1234567890123456', '12345678901234567', '-123456789012345', '0.123456789012345', '0.1234567890123456', INF, -INF, NAN, 0.5, 1.5, -0.5, 2.0, 1e-5, 1e15, 1e16, 3, -3, 0, true, false]));

    $mismatches = [];
    foreach ([['numeric', 'max:3'], ['numeric', 'min:1'], ['integer', 'max:3'], ['integer', 'min:1'], ['numeric', 'min:1', 'max:3'], ['required', 'numeric', 'max:0'], ['nullable', 'integer', 'min:0']] as $rules) {
        foreach ($shapes as $value) {
            if (combinedVerdict($rules, ['f' => $value]) !== laravelVerdict($rules, ['f' => $value])) {
                $mismatches[] = describeRules($rules).' value='.describeValue($value);
            }
        }
    }

    expect($mismatches)->toBe([]);
});

it('agrees on e-mail addresses at every length boundary (local part, each label, domain, total)', function () {
    $cases = [];
    foreach ([1, 10, 63, 64, 65, 70] as $local) {
        foreach ([1, 20, 60, 63, 64, 90, 100, 101, 110, 125, 130, 135, 138, 139, 140, 150, 170, 178, 185, 200, 252, 253, 254] as $domainLength) {
            $rest = $domainLength - 4;
            $labels = [];
            while ($rest > 0) {
                $length = min(63, $rest);
                $labels[] = str_repeat('b', $length);
                $rest -= $length + 1;
            }
            $cases[] = str_repeat('a', $local).'@'.($labels === [] ? 'x' : implode('.', $labels)).'.com';
        }
    }
    foreach ([1, 2, 24, 25, 63, 64] as $tld) {
        $cases[] = 'a@x.'.str_repeat('c', $tld);
        $cases[] = 'a@'.str_repeat('x', 62).'.'.str_repeat('c', $tld);
    }

    $mismatches = [];
    foreach (['email', 'email:rfc', 'required|email'] as $rule) {
        foreach ($cases as $email) {
            $rules = [$rule];
            if (combinedVerdict($rules, ['f' => $email]) !== laravelVerdict($rules, ['f' => $email])) {
                $mismatches[] = strlen($email).' chars, domain '.(strlen($email) - (int) strrpos($email, '@') - 1);
            }
        }
    }

    expect($mismatches)->toBe([]);
});

it('agrees when the field is absent from the data altogether', function () {
    mt_srand(3);
    $mismatches = [];

    for ($i = 0; $i < 3000; $i++) {
        $rules = randomRules();
        $data = ['other' => ['x', 'y', null][mt_rand(0, 2)]];

        if (combinedVerdict($rules, $data) !== laravelVerdict($rules, $data)) {
            $mismatches[] = describeRules($rules).' other='.var_export($data['other'], true);
        }
    }

    expect($mismatches)->toBe([]);
});
