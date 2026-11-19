<?php

/**
 * Mutation test for the fast validation path: deliberately breaks FastRules / RowValidator one way at a
 * time and checks that the differential tests notice. A surviving mutant means a missing test.
 *
 *   php workbench/tools/mutation-test.php [--only=<number>]
 *
 * The source files are restored after every run (and on exit), but run it on a clean working tree.
 */
$root = dirname(__DIR__, 2);
$fast = $root.'/src/Support/FastRules.php';
$validator = $root.'/src/Support/RowValidator.php';

$mutations = [
    ['required ignored on blank values', $fast, 'return ! $required && ($nullable || $value !== null);', 'return $nullable || $value !== null;'],
    ['null accepted without nullable', $fast, 'return ! $required && ($nullable || $value !== null);', 'return ! $required;'],
    ['whitespace-only strings no longer blank', $fast, "(is_string(\$value) && trim(\$value) === '')", "(is_string(\$value) && \$value === '')"],
    ['arrays/objects no longer refused', $fast, 'if ($value !== null && ! is_scalar($value)) {', 'if (false) {'],
    ['max off by one (too permissive)', $fast, 'mb_strlen((string) $value) <= (int) $parameters[0]', 'mb_strlen((string) $value) <= (int) $parameters[0] + 1'],
    ['min off by one (too permissive)', $fast, 'mb_strlen((string) $value) >= (int) $parameters[0]', 'mb_strlen((string) $value) >= (int) $parameters[0] - 1'],
    ['string accepts any scalar', $fast, "'string' => is_string(\$value),", "'string' => is_scalar(\$value),"],
    ['integer = is_numeric', $fast, "'integer' => is_int(\$value) || (is_string(\$value) && filter_var(\$value, FILTER_VALIDATE_INT) !== false),", "'integer' => is_numeric(\$value),"],
    ['numeric accepts any scalar', $fast, "'numeric' => is_numeric(\$value),", "'numeric' => is_scalar(\$value),"],
    ['in() uses loose comparison', $fast, "'in' => in_array((string) \$value, \$parameters, true),", "'in' => in_array(\$value, \$parameters),"],
    ['numeric min compares anything numeric', $fast, 'self::isPlainDecimal($value) && (float) $value >= (int) $parameters[0]', 'is_numeric($value) && (float) $value >= (int) $parameters[0]'],
    ['numeric max compares anything numeric', $fast, 'self::isPlainDecimal($value) && (float) $value <= (int) $parameters[0]', 'is_numeric($value) && (float) $value <= (int) $parameters[0]'],
    ['min/max length instead of value when numeric', $fast, '$checks = array_map(fn (array $check): array => in_array($check[0], [\'min\', \'max\'], true) ?', '$checks = array_map(fn (array $check): array => false ?'],
    ['email domain-length guard removed', $fast, "&& strlen(\$email) - (int) strrpos(\$email, '@') - 1 <= 100", ''],
    ['email total-length guard loosened', $fast, 'strlen($email) <= 180', 'strlen($email) <= 254'],
    ['email local-part length guard removed', $fast, '(?=.{1,64}@)', ''],
    ['email options (dns/strict) compiled', $fast, "if (\$name === 'email' && array_diff(\$params, ['rfc']) !== []) {", 'if (false) {'],
    ['rule objects: failure ignored', $fast, 'if ($failed) {', 'if (false) {'],
    ['data-aware rule objects compiled', $fast, 'if ($rule instanceof ValidatorAwareRule || $rule instanceof DataAwareRule) {', 'if (false) {'],
    ['quoted in() params compiled', $fast, "(\$params === [] || str_contains(\$parameters, '\"'))", '$params === []'],
    ['unknown rules treated as supported', $fast, 'if (! in_array($name, self::SUPPORTED, true)) {', 'if (false) {'],
    ['wildcard fields compiled', $fast, "str_contains(\$field, '*') ? null : self::compileField(\$fieldRules)", 'self::compileField($fieldRules)'],
    ['missing field counted as pass', $fast, 'if (! array_key_exists($field, $data) || ! self::fieldPasses($checks, $data[$field])) {', 'if (array_key_exists($field, $data) && ! self::fieldPasses($checks, $data[$field])) {'],
    ['rules Laravel owns are skipped after a fast pass', $validator, 'return [$slow === [] ? [] : $this->laravelErrors($data, $slow), $data];', 'return [[], $data];'],
    ['validateData() override ignored', $validator, '$customValidation = $declaring->getName() !== Importer::class && ! in_array(ValidatesQuickly::class, class_uses($instance) ?: [], true);', '$customValidation = false;'],
    ['decimal precision guard removed (>15 digits)', $fast, "&& strlen((string) preg_replace('/\\D/', '', \$text)) <= 15", ''],
    ['email domain AND total guards both removed', $fast, "strlen(\$email) <= 180\n            && strlen(\$email) - (int) strrpos(\$email, '@') - 1 <= 100\n            && preg_match(", 'preg_match('],
];

$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = (int) substr($arg, 7);
    }
}

$originals = [$fast => file_get_contents($fast), $validator => file_get_contents($validator)];
register_shutdown_function(function () use ($originals): void {
    foreach ($originals as $file => $content) {
        file_put_contents($file, $content);
    }
});

$killed = 0;
$survived = [];
$skipped = 0;

foreach ($mutations as $index => [$label, $file, $search, $replace]) {
    if ($only !== null && $only !== $index + 1) {
        continue;
    }

    $source = $originals[$file];

    if (! str_contains($source, $search)) {
        echo sprintf("%2d. SKIPPED  %s (pattern not found — update the mutation)\n", $index + 1, $label);
        $skipped++;

        continue;
    }

    file_put_contents($file, str_replace($search, $replace, $source));

    $output = [];
    exec('cd '.escapeshellarg($root).' && IMPORT_WIZARD_FUZZ_CASES=12000 vendor/bin/pest tests/FastRulesTest.php tests/Fuzz/DifferentialTest.php tests/Fuzz/PipelineTest.php --stop-on-failure 2>&1', $output, $status);
    file_put_contents($file, $source);

    if ($status !== 0) {
        $killed++;
        echo sprintf("%2d. killed    %s\n", $index + 1, $label);
    } else {
        $survived[] = $label;
        echo sprintf("%2d. SURVIVED  %s\n", $index + 1, $label);
    }
}

echo sprintf("\n%d killed, %d survived, %d skipped of %d mutations\n", $killed, count($survived), $skipped, count($mutations));
exit($survived === [] ? 0 : 1);
