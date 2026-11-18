<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts a date written in any one of several formats — useful because CSV
 * and Excel exports rarely agree on one (`2024-03-01`, `01/03/2024`, …).
 */
class DateInFormats implements ValidationRule
{
    /**
     * @param  list<string>  $formats  PHP date formats, e.g. ['Y-m-d', 'd/m/Y']
     */
    public function __construct(protected array $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'm/d/Y']) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            foreach ($this->formats as $format) {
                $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return;
                }
            }
        }

        $fail('filament-import-wizard::rules.date')->translate(['formats' => implode(', ', $this->formats)]);
    }
}
