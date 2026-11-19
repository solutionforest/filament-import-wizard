# Rules

## One rules class for forms *and* imports

Write what a record must look like **once**, then use it in the Filament form, the importer and anywhere else.

```php
use SolutionForest\FilamentImportWizard\Rules\CommonRules;
use SolutionForest\FilamentImportWizard\Rules\FieldRules;
use SolutionForest\FilamentImportWizard\Rules\RuleContext;

class ContactRules extends FieldRules
{
    protected function define(RuleContext $context): array
    {
        return [
            'name'  => CommonRules::name(max: 80),
            'email' => [
                ...CommonRules::email(),
                // a form keeps e-mails unique (ignoring the record being edited);
                // an import updates existing contacts, so it must not reject them
                ...($context->isForm() ? [Rule::unique('contacts', 'email')->ignore($context->record)] : []),
            ],
            'phone' => CommonRules::phone(),
        ];
    }

    public function messages(): array   { return ['email.email' => 'Please give a real e-mail address.']; }
    public function attributes(): array { return ['phone' => 'phone number']; }
}
```

**In a Filament form** — rules, required asterisk, messages and attribute names are applied by field name:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components(ContactRules::form([
        TextInput::make('name'),
        TextInput::make('email'),
        TextInput::make('phone'),
    ]));
}
```

**In the importer** — the same definition, by column name:

```php
public static function getColumns(): array
{
    return ContactRules::import([
        ImportColumn::make('name')->requiredMapping(),
        ImportColumn::make('email')->guess(['mail']),
        ImportColumn::make('phone'),
    ]);
}
```

**Anywhere else** (API controller, console command, test):

```php
$data = ContactRules::make()->validate($request->all(), record: $contact);
```

`RuleContext` lets one definition adapt: `isForm()` / `isImport()`, `$record` (the record being edited — resolved lazily in
forms, so edit pages get `unique()->ignore()` for free) and `isEditing()`. Fields without rules are left untouched;
`'required'` also marks the form field required. For the importer's own messages return
`ContactRules::make()->messages()` from `getValidationMessages()`.

## Building blocks

Plain Laravel rules, usable in any validator:

- `CommonRules::email() / phone() / name() / url() / integer() / date() / oneOf()` — rule sets that return lists you can extend.
- `Rules\PhoneNumber`, `Rules\DateInFormats`, `Rules\OneOfIgnoringCase` — `ValidationRule` classes with translatable messages
  (`filament-import-wizard::rules`).

Write your own `ValidationRule` and put it in a definition.

## File-level checks

Some problems only show up across rows. Add `RowCheck`s to the page; they run during Review next to the rules:

```php
use SolutionForest\FilamentImportWizard\Checks\UniqueInFile;

protected function getRowChecks(): array
{
    return [new UniqueInFile(['email'])];   // or ['first_name', 'last_name'] for a combination
}
```

`UniqueInFile` remembers what it has seen in a store outside PHP, so memory stays flat for millions of rows. Behind a
load balancer set `IMPORT_WIZARD_SEEN_STORE=database` ([Scaling](scaling.md)). Implement `Checks\RowCheck` for your own checks.

> File-level checks inform Review only. The queued import runs slices in parallel and uses your importer's
> `resolveRecord()`, so a duplicate row updates the earlier one — fix the file if that isn't what you want.
