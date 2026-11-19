<?php

namespace Workbench\App\Rules;

use Illuminate\Validation\Rule;
use SolutionForest\FilamentImportWizard\Rules\CommonRules;
use SolutionForest\FilamentImportWizard\Rules\FieldRules;
use SolutionForest\FilamentImportWizard\Rules\RuleContext;

/**
 * The single definition of what a contact looks like. A Filament form uses it
 * with `ContactRules::form([...])`, the importer with `ContactRules::import([...])`.
 */
class ContactRules extends FieldRules
{
    protected function define(RuleContext $context): array
    {
        return [
            'name' => CommonRules::name(max: 80),
            'email' => [
                ...CommonRules::email(),
                // Forms keep e-mails unique; an import updates existing contacts instead.
                ...($context->isForm() ? [Rule::unique('contacts', 'email')->ignore($context->record)] : []),
            ],
            'phone' => CommonRules::phone(),
        ];
    }
}
