<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Pages;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use LogicException;
use SolutionForest\FilamentImportWizard\Actions\ImportWizardAction;
use SolutionForest\FilamentImportWizard\Concerns\ManagesImportWizard;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;

/**
 * A four step import page: Upload → Map → Review → Import.
 *
 * Extend it, point it at a Filament Importer and register it on a panel, either
 * with `->pages([...])` or through {@see ImportWizardPlugin}:
 *
 *     class ImportContacts extends ImportWizardPage
 *     {
 *         protected static ?string $importer = ContactImporter::class;
 *     }
 *
 * The file is never loaded into memory. Review validates it in small slices
 * driven by polling, and the import itself is queued through Filament's own
 * ImportDispatcher, one job per slice. For a slide-over opened from a table or
 * page header instead of a full page, see {@see ImportWizardAction}.
 *
 * @property-read Schema $content
 */
abstract class ImportWizardPage extends Page
{
    use ManagesImportWizard;

    /**
     * @var class-string<Importer>|null
     */
    protected static ?string $importer = null;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-import-wizard::pages.import-wizard';

    /**
     * @return class-string<Importer>
     */
    public static function getImporter(): string
    {
        return static::$importer ?? throw new LogicException(static::class.' must define the $importer property.');
    }

    public function getTitle(): string
    {
        return __('filament-import-wizard::import.title', ['label' => $this->getEntityLabel()]);
    }

    protected function importerClass(): string
    {
        return static::getImporter();
    }

    public function mount(): void
    {
        $this->content->fill();
    }
}
