<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Rules;

use Illuminate\Database\Eloquent\Model;

/**
 * Tells a {@see FieldRules} definition where its rules are being used, so one
 * definition can adapt: a unique rule must ignore the record being edited in a
 * form, and should usually be left out of an import that updates existing rows.
 */
final readonly class RuleContext
{
    public function __construct(
        public ?Model $record = null,
        public bool $import = false,
    ) {}

    public function isImport(): bool
    {
        return $this->import;
    }

    public function isForm(): bool
    {
        return ! $this->import;
    }

    /**
     * True when an existing record is being edited (form edit page).
     */
    public function isEditing(): bool
    {
        return $this->record instanceof Model && $this->record->exists;
    }
}
