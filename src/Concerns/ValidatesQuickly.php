<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Concerns;

use Illuminate\Support\Facades\Validator;
use SolutionForest\FilamentImportWizard\Support\FastRules;

/**
 * Use in a Filament importer to make its own `validateData()` — which runs for
 * every row of the real import — skip Laravel's validator for rows that
 * certainly pass (see {@see FastRules}). Rows that fail, or use rules the fast
 * path cannot reproduce, take Laravel's normal path, so errors and messages
 * are unchanged. The Review step uses the same fast path automatically.
 *
 *     class ContactImporter extends Importer
 *     {
 *         use ValidatesQuickly;
 *     }
 */
trait ValidatesQuickly
{
    private ?FastRules $quickRules = null;

    public function validateData(): void
    {
        $this->quickRules ??= FastRules::compile($this->getValidationRules());

        if (! $this->quickRules->passes($this->data)) {
            parent::validateData();

            return;
        }

        $slow = $this->quickRules->slowRules();

        if ($slow !== []) {
            Validator::validate($this->data, $slow, $this->getValidationMessages(), $this->getValidationAttributes());
        }
    }
}
