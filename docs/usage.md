# Usage

## The page

Create a normal Filament importer, then a page that points at it:

```php
use SolutionForest\FilamentImportWizard\Pages\ImportWizardPage;

class ImportContacts extends ImportWizardPage
{
    protected static ?string $importer = ContactImporter::class;
}
```

The title, navigation and authorization (`canAccess()`, `shouldRegisterNavigation()`) work like any Filament page.

## Register it

With the plugin (recommended — gives per-panel settings):

```php
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;

$panel->plugin(
    ImportWizardPlugin::make()
        ->pages([ImportContacts::class])
        ->disk('s3')            // where uploads are stored
        ->chunkSize(1000),      // rows per queued job
);
```

…or just `->pages([ImportContacts::class])` for the defaults.

## Several panels

Register the plugin on each panel with its own settings. Settings come from the panel serving the request, uploads are
kept under `{directory}/{panel-id}/`, and a path from another panel's directory is rejected. One page class can be shared.

```php
// AdminPanelProvider
ImportWizardPlugin::make()->pages([ImportContacts::class])->audit()->chunkedUploads();
// StaffPanelProvider
ImportWizardPlugin::make()->pages([ImportContacts::class])->disk('staff')->requireApproval();
```

## Page options

Override on your page class when one page needs something different:

| Method | Purpose |
| --- | --- |
| `getUploadDisk()` / `getUploadDirectory()` | Where this page stores uploads |
| `getChunkSize()` | Rows per queued job |
| `getRowsPerReviewRequest()` | Rows validated per poll in Review |
| `getRowChecks()` | File-level checks, e.g. duplicates — see [Rules](rules.md) |
| `getAcceptedFileTypes()` | Allowed MIME types |
| `getEntityLabel()` | The word(s) in the page title — override to translate ([Localization](localization.md)) |

Every plugin and config option is listed in [Configuration](configuration.md).
