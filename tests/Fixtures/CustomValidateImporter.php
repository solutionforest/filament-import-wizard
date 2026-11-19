<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Illuminate\Validation\ValidationException;

/** Overrides validateData(): the fast path must step aside. */
class CustomValidateImporter extends MatrixImporter
{
    public function validateData(): void
    {
        throw ValidationException::withMessages(['a' => ['custom rule always fails']]);
    }
}
