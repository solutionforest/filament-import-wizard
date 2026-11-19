# Import without writing an importer

For a quick start, point the action at a model. The importer is generated from the model's table:

```php
ImportWizardAction::make()->forModel(Product::class)
```

or, for the headless API / console, `ModelImporters::for(Product::class)` returns the importer class to pass wherever an importer is expected.

## What is generated

One import column per **fillable** table column. Primary keys, `created_at` / `updated_at`, `deleted_at` and `remember_token` are skipped.

| Column in the table | Import column |
| --- | --- |
| integer | integer cast, `integer` rule |
| decimal / float | numeric cast, `numeric` rule |
| boolean / `tinyint(1)` | boolean cast, `boolean` rule |
| date / datetime / timestamp | `date` rule |
| `varchar(n)` | `max:n` rule (on drivers that report the length: MySQL, Postgres) |
| name looks like an e-mail (`email`, `contact_email`) | `email` rule |
| NOT NULL, no default | `required` rule, mapping required |
| NOT NULL with a default | a blank cell uses the default instead of writing NULL |
| nullable | `nullable` rule |

Rows are **created**, never updated. Headers are auto-mapped like any importer's.

## When to write a real importer

Use an importer class as soon as you need relationships, upserts (`resolveRecord()`), rules beyond the schema (`FieldRules`, `UniqueInFile`), translated fields ([`LocaleColumns`](translatable.md)),
options, or a custom completion message. The generated class is a normal Filament importer (`ModelImporter`), so moving on is a copy-and-edit.
