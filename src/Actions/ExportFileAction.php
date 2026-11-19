<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\Imports\Importer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use SolutionForest\FilamentImportWizard\Export\FileExport;
use SolutionForest\FilamentImportWizard\Importers\ModelImporters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The export counterpart of {@see ImportWizardAction}: a button that downloads the records of
 * a list page (with its filters, search and sorting applied) as Excel or CSV, in the exact
 * layout the importer reads back — so an exported file can be edited and imported again.
 *
 *     protected function getHeaderActions(): array
 *     {
 *         return [
 *             ExportFileAction::make()->importer(ContactImporter::class),
 *             ImportWizardAction::make()->importer(ContactImporter::class),
 *         ];
 *     }
 */
class ExportFileAction extends Action
{
    /** @var class-string<Importer>|Closure|null */
    protected string|Closure|null $importer = null;

    protected string|Closure|null $filename = null;

    public static function getDefaultName(): ?string
    {
        return 'exportFile';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-import-wizard::export.action.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament-import-wizard::export.action.heading'))
            ->modalSubmitActionLabel(__('filament-import-wizard::export.action.submit'))
            ->schema([
                Select::make('format')
                    ->label(__('filament-import-wizard::export.format'))
                    ->options(['xlsx' => __('filament-import-wizard::export.formats.xlsx'), 'csv' => __('filament-import-wizard::export.formats.csv')])
                    ->default('xlsx')
                    ->required()
                    ->native(false),
                Toggle::make('template')
                    ->label(__('filament-import-wizard::export.template'))
                    ->helperText(__('filament-import-wizard::export.template_help')),
            ])
            ->action(function (array $data, mixed $livewire): StreamedResponse {
                $format = ($data['format'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
                $importer = $this->getImporter();
                $export = FileExport::for($importer)->format($format)->template((bool) ($data['template'] ?? false));

                $query = $this->resolveQuery($livewire);

                if ($query instanceof Builder) {
                    $export->query($query);
                }

                return $export->download($this->resolveFilename($importer, $format, (bool) ($data['template'] ?? false)));
            });
    }

    /**
     * @param  class-string<Importer>|Closure  $importer
     */
    public function importer(string|Closure $importer): static
    {
        $this->importer = $importer;

        return $this;
    }

    /**
     * The download name without extension, e.g. `'contacts'` (default: the importer's name + date).
     */
    public function filename(string|Closure $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * @return class-string<Importer>
     */
    public function getImporter(): string
    {
        $importer = $this->evaluate($this->importer);

        if (! is_string($importer) || ! is_subclass_of($importer, Importer::class)) {
            throw new LogicException('ExportFileAction needs a Filament importer: ->importer(ContactImporter::class).');
        }

        return $importer;
    }

    /**
     * The list page's own query — filters, search and sorting included — when the action lives on one.
     *
     * @return Builder<Model>|null
     */
    protected function resolveQuery(mixed $livewire): ?Builder
    {
        if (is_object($livewire) && method_exists($livewire, 'getFilteredSortedTableQuery')) {
            $query = $livewire->getFilteredSortedTableQuery();

            return $query instanceof Builder ? $query : null;
        }

        return null;
    }

    /**
     * @param  class-string<Importer>  $importer
     */
    protected function resolveFilename(string $importer, string $format, bool $template): string
    {
        $base = $this->evaluate($this->filename);
        $base = is_string($base) && $base !== ''
            ? $base
            : Str::of(ModelImporters::basename($importer))->beforeLast('Importer')->kebab()->plural()->toString().($template ? '-template' : '-'.now()->format('Y-m-d'));

        return $base.'.'.$format;
    }
}
