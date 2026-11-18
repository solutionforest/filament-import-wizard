<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number as people type it: optional leading +, then 7–15 digits,
 * with spaces, dots, dashes and brackets ignored.
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('filament-import-wizard::rules.phone')->translate();

            return;
        }

        $normalized = preg_replace('/[\s().\-]/', '', (string) $value);

        if ($normalized === null || preg_match('/^\+?\d{7,15}$/', $normalized) !== 1) {
            $fail('filament-import-wizard::rules.phone')->translate();
        }
    }
}
