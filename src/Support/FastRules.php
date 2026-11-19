<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A conservative, compiled pre-check for the common single-field rules.
 *
 * Laravel's validator costs ~40 µs per field per row, which dominates Review.
 * This class answers one question only — "does this field *certainly* pass?" —
 * natively and in a few microseconds. It is built so it can never be more
 * permissive than Laravel:
 *
 *  - only rules whose Laravel semantics are reproduced exactly are compiled
 *    (required, nullable, string, integer, numeric, min/max, in, email, and
 *    plain `ValidationRule` objects); one unknown rule leaves the whole field
 *    to Laravel;
 *  - any doubt (odd value, unusual shape) answers "not sure", and the caller
 *    then runs Laravel's real validation for that row, which also produces
 *    the messages — so results and messages are identical with or without it.
 */
final class FastRules
{
    /** Rules that only inspect their own field and are reproduced natively. */
    private const SUPPORTED = ['required', 'nullable', 'string', 'integer', 'numeric', 'min', 'max', 'in', 'email', 'bail', 'sometimes'];

    /**
     * @param  array<string, list<array{0: string, 1: list<string>|ValidationRule}>>  $fast  compiled fields
     * @param  array<string, array<mixed>>  $slow  fields left to Laravel
     */
    private function __construct(private array $fast, private array $slow) {}

    /**
     * @param  array<string, array<mixed>>  $rules  field => rules, as the importer returns them
     */
    public static function compile(array $rules): self
    {
        $fast = [];
        $slow = [];

        foreach ($rules as $field => $fieldRules) {
            $compiled = str_contains($field, '*') ? null : self::compileField($fieldRules);

            if ($compiled === null) {
                $slow[$field] = $fieldRules;
            } else {
                $fast[$field] = $compiled;
            }
        }

        return new self($fast, $slow);
    }

    public function hasFastFields(): bool
    {
        return $this->fast !== [];
    }

    /**
     * The fields Laravel must still validate (rules the fast path cannot reproduce).
     *
     * @return array<string, array<mixed>>
     */
    public function slowRules(): array
    {
        return $this->slow;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return bool true when every compiled field certainly passes
     */
    public function passes(array $data): bool
    {
        foreach ($this->fast as $field => $checks) {
            if (! array_key_exists($field, $data) || ! self::fieldPasses($checks, $data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $rules
     * @return list<array{0: string, 1: list<string>|ValidationRule}>|null
     */
    private static function compileField(array $rules): ?array
    {
        $checks = [];
        $hasNumeric = false;

        foreach ($rules as $rule) {
            if ($rule instanceof ValidationRule) {
                // Objects that need the validator or the rest of the data cannot run standalone.
                if ($rule instanceof ValidatorAwareRule || $rule instanceof DataAwareRule) {
                    return null;
                }

                $checks[] = ['object', $rule];

                continue;
            }

            if (! is_string($rule)) {
                return null; // closures, Rule::unique(), etc.
            }

            // A pipe inside an array element is not split by Laravel either: it is one (unknown) rule name.
            foreach ([$rule] as $single) {
                [$name, $parameters] = array_pad(explode(':', $single, 2), 2, '');
                $name = strtolower($name);

                if (! in_array($name, self::SUPPORTED, true)) {
                    return null;
                }

                $params = $parameters === '' ? [] : explode(',', $parameters);

                if ($name === 'email' && array_diff($params, ['rfc']) !== []) {
                    return null; // dns / strict / spoof / filter need Laravel
                }

                if (in_array($name, ['min', 'max'], true) && (count($params) !== 1 || ! ctype_digit($params[0]))) {
                    return null;
                }

                if ($name === 'in' && ($params === [] || str_contains($parameters, '"'))) {
                    return null;
                }

                $hasNumeric = $hasNumeric || in_array($name, ['integer', 'numeric'], true);
                $checks[] = [$name, $params];
            }
        }

        // `min` / `max` mean "value" for numbers and "length" for strings, as in Laravel.
        if ($hasNumeric) {
            $checks = array_map(fn (array $check): array => in_array($check[0], ['min', 'max'], true) ? [$check[0].'_numeric', $check[1]] : $check, $checks);
        }

        return $checks;
    }

    /**
     * @param  list<array{0: string, 1: list<string>|ValidationRule}>  $checks
     */
    private static function fieldPasses(array $checks, mixed $value): bool
    {
        $required = false;
        $nullable = false;

        foreach ($checks as [$name]) {
            $required = $required || $name === 'required';
            $nullable = $nullable || $name === 'nullable';
        }

        $isBlank = $value === null || (is_string($value) && trim($value) === '');

        if ($isBlank) {
            // Only implicit rules (required) run on blank values; anything else is Laravel's call.
            return ! $required && ($nullable || $value !== null);
        }

        foreach ($checks as [$name, $parameters]) {
            if ($parameters instanceof ValidationRule) {
                $failed = false;
                $parameters->validate('field', $value, function (string $message) use (&$failed): PotentiallyTranslatedString {
                    $failed = true;

                    return new PotentiallyTranslatedString($message, app('translator'));
                });

                if ($failed) {
                    return false;
                }

                continue;
            }

            $ok = match ($name) {
                'string' => is_string($value),
                'integer' => is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false),
                'numeric' => is_numeric($value),
                'min' => is_scalar($value) && mb_strlen((string) $value) >= (int) $parameters[0],
                'max' => is_scalar($value) && mb_strlen((string) $value) <= (int) $parameters[0],
                'min_numeric' => is_numeric($value) && $value + 0 >= (int) $parameters[0],
                'max_numeric' => is_numeric($value) && $value + 0 <= (int) $parameters[0],
                'in' => is_scalar($value) && in_array((string) $value, $parameters, true),
                'email' => is_string($value) && self::isSafeEmail($value),
                default => true, // required / nullable / bail / sometimes: handled above or no-ops here
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Accepts only addresses that RFC validation certainly accepts (plain
     * dot-atom local part, ordinary hostname with a letter-only TLD). Everything
     * else answers false so Laravel decides.
     */
    public static function isSafeEmail(string $email): bool
    {
        return strlen($email) <= 254
            && preg_match('/^(?=.{1,64}@)[A-Za-z0-9]+(?:[._+-][A-Za-z0-9]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,24}$/D', $email) === 1;
    }
}
