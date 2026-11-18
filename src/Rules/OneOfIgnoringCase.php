<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be one of a fixed list, compared case-insensitively and
 * ignoring surrounding whitespace ("Active ", "active" and "ACTIVE" all match).
 */
class OneOfIgnoringCase implements ValidationRule
{
    /**
     * @param  list<string>  $allowed
     */
    public function __construct(protected array $allowed) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowed = array_map(fn (string $option): string => mb_strtolower($option), $this->allowed);

        if (! is_string($value) || ! in_array(mb_strtolower(trim($value)), $allowed, true)) {
            $fail('filament-import-wizard::rules.one_of')->translate(['values' => implode(', ', $this->allowed)]);
        }
    }
}
