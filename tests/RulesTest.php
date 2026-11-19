<?php

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\Contact;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactImporter;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ContactRules;
use SolutionForest\FilamentImportWizard\Tests\Fixtures\ImportContacts;

it('gives the importer the same rules as the definition', function () {
    $columns = collect(ContactImporter::getColumns())->keyBy(fn ($column) => $column->getName());

    expect($columns['email']->getDataValidationRules())->toEqual(ContactRules::make()->for('email', import: true))
        ->and($columns['name']->getDataValidationRules())->toEqual(ContactRules::make()->for('name', import: true));

    $validator = new RowValidator(ContactImporter::class, ['name' => 'n', 'email' => 'e', 'phone' => 'p']);

    expect($validator->validate(['n' => 'Ana', 'e' => 'ana@x.com', 'p' => '+1 415 555 0101']))->toBe([])
        ->and($validator->validate(['n' => 'Ana', 'e' => 'nope', 'p' => '+1 415 555 0101']))->toHaveKey('email')
        ->and($validator->validate(['n' => 'Ana', 'e' => 'ana@x.com', 'p' => 'call me']))->toHaveKey('phone');
});

it('lets an import update an existing contact but makes a form keep e-mails unique', function () {
    $existing = Contact::create(['name' => 'Ana', 'email' => 'ana@x.com']);
    $rules = ContactRules::make();

    // Import context: no uniqueness rule, so re-importing an existing e-mail is fine.
    $importValidator = new RowValidator(ContactImporter::class, ['name' => 'n', 'email' => 'e']);
    expect($importValidator->validate(['n' => 'Ana B', 'e' => 'ana@x.com']))->toBe([]);

    // Form context: a new record may not reuse it…
    expect(fn () => $rules->validate(['name' => 'Ben', 'email' => 'ana@x.com']))->toThrow(ValidationException::class);

    // …but the record being edited may keep its own.
    expect($rules->validate(['name' => 'Ana B', 'email' => 'ana@x.com'], record: $existing))->toHaveKey('email');
});

it('applies the same rules, messages and attribute names to Filament form fields', function () {
    $existing = Contact::create(['name' => 'Ana', 'email' => 'ana@x.com']);

    $fields = ContactRules::form([
        TextInput::make('name'),
        TextInput::make('email'),
        TextInput::make('phone'),
        TextInput::make('nickname'),
    ]);

    $schema = Schema::make(app(ImportContacts::class))->components($fields)->model($existing)->record($existing);
    $components = collect($schema->getFlatComponents())->keyBy(fn ($component) => $component->getName());

    expect($components['name']->isRequired())->toBeTrue()
        ->and($components['nickname']->getValidationRules())->not->toContain('email:rfc')
        ->and(collect($components['email']->getValidationRules())->map(fn ($rule) => is_string($rule) ? $rule : $rule::class)->all())
        ->toContain('email:rfc')
        ->toContain(Unique::class)
        ->and($components['phone']->getValidationAttribute())->toBe('phone number');
});

it('validates plain data with the same definition', function () {
    expect(ContactRules::make()->validate(['name' => 'Ana', 'email' => 'ana@x.com', 'phone' => null]))
        ->toBe(['name' => 'Ana', 'email' => 'ana@x.com', 'phone' => null]);

    try {
        ContactRules::make()->validate(['name' => 'Ana', 'email' => 'nope']);
    } catch (ValidationException $exception) {
        expect($exception->errors()['email'][0])->toBe('Please give a real e-mail address.');
    }
});
