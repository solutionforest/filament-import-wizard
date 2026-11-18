<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Str;

class ColumnMapper
{
    /**
     * Guess which file column belongs to each importer column.
     *
     * Order of preference: exact match on the column name / label / the
     * importer's own `guess()` aliases, then a normalised match ("E-mail"
     * == "email"), then a header that ends with the name, then a fuzzy match above $threshold percent. A file column
     * is never assigned twice.
     *
     * @param  array<ImportColumn>  $columns
     * @param  array<int, string>  $headers
     * @return array<string, string> importer column name => file column
     */
    public function guess(array $columns, array $headers, float $threshold = 80.0): array
    {
        $map = [];
        $taken = [];

        $passes = [
            fn (string $guess, string $header): bool => Str::lower($header) === $guess,
            fn (string $guess, string $header): bool => $this->normalize($header) === $this->normalize($guess),
            // "Full name" → name, "Work e-mail" → email
            fn (string $guess, string $header): bool => strlen($this->normalize($guess)) >= 4 && str_ends_with($this->normalize($header), $this->normalize($guess)),
            fn (string $guess, string $header): bool => $this->similarity($header, $guess) >= $threshold,
        ];

        foreach ($passes as $matches) {
            foreach ($columns as $column) {
                if (isset($map[$column->getName()])) {
                    continue;
                }

                foreach ($column->getGuesses() as $guess) {
                    $header = collect($headers)
                        ->first(fn (string $header): bool => ! isset($taken[$header]) && filled($header) && $matches($guess, $header));

                    if ($header !== null) {
                        $map[$column->getName()] = $header;
                        $taken[$header] = true;

                        break;
                    }
                }
            }
        }

        return $map;
    }

    protected function normalize(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value)));
    }

    protected function similarity(string $a, string $b): float
    {
        $a = $this->normalize($a);
        $b = $this->normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return $percent;
    }
}
