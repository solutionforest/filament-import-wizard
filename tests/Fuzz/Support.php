<?php

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use SolutionForest\FilamentImportWizard\Rules\DateInFormats;
use SolutionForest\FilamentImportWizard\Rules\OneOfIgnoringCase;
use SolutionForest\FilamentImportWizard\Rules\PhoneNumber;
use SolutionForest\FilamentImportWizard\Support\FastRules;

/** A ValidationRule that fails for one value — proves object rules are honoured. */
final class FailsOnBoom implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === 'boom' || $value === 7 || $value === '7') {
            $fail('boom');
        }
    }
}

/** A rule that needs the other fields: the fast path must refuse to run it standalone. */
final class NeedsData implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($this->data['other'] ?? null) === 'x') {
            $fail('data-aware');
        }
    }
}

/**
 * What Laravel says about one field: 'pass', 'fail' or 'throws' (a rule that cannot
 * evaluate the value — RowValidator reports that as an error too).
 *
 * @param  array<mixed>  $rules
 * @param  array<string, mixed>  $data
 */
function laravelVerdict(array $rules, array $data, string $field = 'f'): string
{
    try {
        return Validator::make($data, [$field => $rules])->passes() ? 'pass' : 'fail';
    } catch (Throwable) {
        return 'throws';
    }
}

/**
 * What the fast path + Laravel-for-the-rest combination decides, the way RowValidator uses it.
 *
 * @param  array<mixed>  $rules
 * @param  array<string, mixed>  $data
 */
function combinedVerdict(array $rules, array $data, string $field = 'f'): string
{
    $fast = FastRules::compile([$field => $rules]);

    if (! $fast->passes($data)) {
        return laravelVerdict($rules, $data, $field);
    }

    $slow = $fast->slowRules();

    if ($slow === []) {
        return 'pass';
    }

    try {
        return Validator::make($data, $slow)->passes() ? 'pass' : 'fail';
    } catch (Throwable) {
        return 'throws';
    }
}

/** @return list<mixed> */
function valuePool(): array
{
    $strings = [
        '', ' ', '  ', "\t", "\n", "\r\n", "\u{00a0}", "\u{3000}", '0', '00', '1', '7', '12', '-1', '-0', '+5', '007', '1.0', '1.5', '.5', '5.', '1e3', '1E3', '1e-3', '0x1A', '0b11', '1_000', '1,000',
        ' 12', '12 ', ' 12 ', "12\n", "\n12", '１２', '١٢', 'NaN', 'INF', '-INF', '9223372036854775807', '9223372036854775808', '-9223372036854775808', '99999999999999999999', '0.1', '1.7976931348623157E+308',
        'a', 'ab', 'abc', 'ABCDEFGHIJ', 'ABCDEFGHIJK', 'a b', 'a-b', "tab\there", 'null', 'NULL', 'true', 'false', 'TRUE', 'yes', 'no', 'on', 'off',
        'red', 'RED', 'Red ', 'green', 'blue', 'a,b', '"a"', "'a'", '\\', '%', '<b>', '日本語', 'éééééééééé', 'ééééééééééé', "e\u{0301}", '😀', '😀😀😀😀😀', 'boom',
        'p', 'q', 'x1', 'xray', '+1 415 555 0101', '+14155550101', '(415) 555-0101', '12345', 'call me', '2024-01-31', '31/12/2024', '2024-02-30', '12/31/2024',
        str_repeat('a', 4), str_repeat('a', 5), str_repeat('a', 6), str_repeat('a', 50), str_repeat('a', 100), str_repeat('a', 101), str_repeat('é', 100), str_repeat('😀', 3),
    ];

    $emails = [
        'a@b.co', 'ana@x.com', 'ana.b+tag@sub.example.org', 'A@B.CO', 'a_b@x.io', 'a-b@x-y.com', 'a.b.c@x.y.z.com', '1@2.co', 'x@y.museum', 'x@y.travel', 'x@y.XN', 'x@y.c', 'x@y.123', 'x@123.123.123.123', 'x@[1.2.3.4]', 'x@[IPv6:::1]',
        'bad', 'a@', '@x.com', 'a@x', 'a@@x.com', 'a..b@x.com', '.a@x.com', 'a.@x.com', 'a@-x.com', 'a@x-.com', 'a@x..com', 'a@.com', 'a@x.com.', 'a b@x.com', 'a@x .com', ' a@x.com', 'a@x.com ', "a@x.com\n",
        'ä@x.com', 'a@ä.com', 'a@x.cöm', '"q"@x.com', '"a b"@x.com', 'a(comment)@x.com', 'a@x(comment).com', '\\a@x.com', 'a\\@b@x.com', '!#$%&\'*+/=?^_`{|}~-@x.com', 'a@x.com,b@y.com', 'mailto:a@x.com', '<a@x.com>', 'Name <a@x.com>',
        str_repeat('a', 64).'@x.com', str_repeat('a', 65).'@x.com', 'a@'.str_repeat('b', 63).'.com', 'a@'.str_repeat('b', 64).'.com', str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.com',
        str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 58).'.com',
    ];

    $other = [null, true, false, 0, 1, -1, 7, 12, 100, 101, 0.0, 1.0, 1.5, -2.5, 1e3, PHP_INT_MAX, PHP_INT_MIN, NAN, INF, [], [''], ['a'], [1, 2], ['a' => 'b'], new stdClass];

    return [...$strings, ...$emails, ...$other];
}

/**
 * A random field rule set: supported rules, rules only Laravel can do, objects, in any order.
 *
 * @return array<mixed>
 */
function randomRules(): array
{
    $sizes = [0, 1, 2, 3, 5, 10, 12, 100];
    $pickSize = fn () => $sizes[mt_rand(0, count($sizes) - 1)];

    $makers = [
        fn () => 'required', fn () => 'nullable', fn () => 'string', fn () => 'integer', fn () => 'numeric', fn () => 'bail', fn () => 'sometimes',
        fn () => 'min:'.$pickSize(), fn () => 'max:'.$pickSize(), fn () => 'email', fn () => 'email:rfc',
        fn () => 'in:'.['red,green,blue', '1,2,3', 'a', '0,1', 'x,7,y', ' a, b', '007,12'][mt_rand(0, 6)],
        fn () => new PhoneNumber, fn () => new DateInFormats(['Y-m-d', 'd/m/Y']), fn () => new OneOfIgnoringCase(['red', 'green']), fn () => new FailsOnBoom,
        // Only Laravel can judge these (or they are deliberately unsupported):
        fn () => 'regex:/^x/', fn () => 'alpha', fn () => 'boolean', fn () => 'array', fn () => 'date', fn () => 'url', fn () => 'between:1,5', fn () => 'size:3', fn () => 'digits:3',
        fn () => 'email:dns', fn () => 'email:strict', fn () => 'not_in:red', fn () => 'lowercase', fn () => 'ascii', fn () => 'filled', fn () => 'present', fn () => 'decimal:0,2',
        fn () => 'min:abc', fn () => 'max:', fn () => 'in:', fn () => 'in:"a,b",c', fn () => 'Required', fn () => 'STRING', fn () => 'nullable|string', fn () => new NeedsData,
        fn () => fn ($attribute, $value, $fail) => $value === 'boom' ? $fail('closure boom') : null,
    ];

    $rules = [];
    for ($n = mt_rand(1, 5); $n > 0; $n--) {
        $rules[] = $makers[mt_rand(0, count($makers) - 1)]();
    }

    return $rules;
}

function describeRules(array $rules): string
{
    return json_encode(array_map(fn ($rule) => is_object($rule) ? ($rule instanceof Closure ? 'Closure' : get_class($rule)) : $rule, $rules));
}

function describeValue(mixed $value): string
{
    return is_object($value) ? get_class($value) : var_export($value, true);
}

function fuzzCases(): int
{
    return (int) (getenv('IMPORT_WIZARD_FUZZ_CASES') ?: 12000);
}
