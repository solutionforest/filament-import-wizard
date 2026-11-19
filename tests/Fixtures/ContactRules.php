<?php

namespace SolutionForest\FilamentImportWizard\Tests\Fixtures;

use Illuminate\Validation\Rule;
use SolutionForest\FilamentImportWizard\Rules\CommonRules;
use SolutionForest\FilamentImportWizard\Rules\FieldRules;
use SolutionForest\FilamentImportWizard\Rules\RuleContext;

class ContactRules extends FieldRules
{
    protected function define(RuleContext $context): array
    {
        return [
            'name' => CommonRules::name(max: 50),
            'email' => [
                ...CommonRules::email(),
                // A form must keep emails unique (ignoring the record being edited);
                // an import updates existing contacts, so it must not reject them.
                ...($context->isForm() ? [Rule::unique('contacts', 'email')->ignore($context->record)] : []),
            ],
            'phone' => CommonRules::phone(),
        ];
    }

    public function messages(): array
    {
        return ['email.email' => 'Please give a real e-mail address.'];
    }

    public function attributes(): array
    {
        return ['phone' => 'phone number'];
    }
}
