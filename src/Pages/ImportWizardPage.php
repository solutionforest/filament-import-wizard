<?php

namespace SolutionForest\FilamentImportWizard\Pages;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SolutionForest\FilamentImportWizard\Support\ColumnMapper;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;
use SolutionForest\FilamentImportWizard\Support\RowValidator;
use SolutionForest\FilamentImportWizard\Support\SpreadsheetConverter;

/**
 * A four step import page: Upload → Map → Review → Import.
 *
 * Extend it, point it at a Filament Importer and register it like any page:
 *
 *     class ImportContacts extends ImportWizardPage
 *     {
 *         protected static ?string $importer = ContactImporter::class;
 *     }
 *
 * The file is never loaded into memory. Review validates it in small slices
 * driven by polling, and the import itself is queued through Filament's own
 * ImportDispatcher, one job per slice.
 */
abstract class ImportWizardPage extends Page
{
    /**
     * @var class-string<Importer>|null
     */
    protected static ?string $importer = null;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-import-wizard::pages.import-wizard';

    public const STEP_UPLOAD = 1;

    public const STEP_MAP = 2;

    public const STEP_REVIEW = 3;

    public const STEP_IMPORT = 4;

    /** @var array<string, mixed> */
    public ?array $data = [];

    #[Locked]
    public int $step = self::STEP_UPLOAD;

    #[Locked]
    public string $filePath = '';

    #[Locked]
    public string $fileName = '';

    #[Locked]
    public string $delimiter = ',';

    /** @var array<int, string> */
    #[Locked]
    public array $headers = [];

    /** @var array<int, array<string, string|null>> */
    #[Locked]
    public array $sample = [];

    #[Locked]
    public int $totalRows = 0;

    /** Review progress: byte offset reached, rows checked, rows with errors. */
    #[Locked]
    public int $reviewOffset = 0;

    #[Locked]
    public int $reviewedRows = 0;

    #[Locked]
    public int $invalidRows = 0;

    #[Locked]
    public bool $reviewFinished = false;

    /** @var array<int, array{row: int, errors: array<string, array<int, string>>}> */
    #[Locked]
    public array $errorRows = [];

    /** @var array<string, int> */
    #[Locked]
    public array $errorsByColumn = [];

    #[Locked]
    public ?int $importId = null;

    protected function getRowsPerReviewRequest(): int
    {
        return 2000;
    }

    protected function getChunkSize(): int
    {
        return 500;
    }

    protected function getMaxCollectedErrors(): int
    {
        return 50;
    }

    protected function getUploadDisk(): string
    {
        return config('filament.default_filesystem_disk', 'local');
    }

    protected function getUploadDirectory(): string
    {
        return 'import-wizard';
    }

    protected function getMaxUploadSizeInKilobytes(): ?int
    {
        return null;
    }

    /**
     * @return class-string<Importer>
     */
    public static function getImporter(): string
    {
        return static::$importer ?? throw new \LogicException(static::class.' must define the $importer property.');
    }

    public function getTitle(): string
    {
        return __('filament-import-wizard::import.title', [
            'label' => str(class_basename(static::getImporter()))->beforeLast('Importer')->headline()->lower()->plural()->toString(),
        ]);
    }

    /**
     * @return array<ImportColumn>
     */
    public function getImportColumns(): array
    {
        return static::getImporter()::getColumns();
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                View::make('filament-import-wizard::steps.stepper'),
                Section::make()
                    ->visible(fn (): bool => $this->step === self::STEP_UPLOAD)
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('filament-import-wizard::import.upload.label'))
                            ->helperText(__('filament-import-wizard::import.upload.helper'))
                            ->disk($this->getUploadDisk())
                            ->directory($this->getUploadDirectory())
                            ->acceptedFileTypes(['text/csv', 'text/x-csv', 'application/csv', 'application/x-csv', 'text/comma-separated-values', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.oasis.opendocument.spreadsheet'])
                            ->maxSize($this->getMaxUploadSizeInKilobytes())
                            ->storeFileNamesIn('fileName')
                            ->required()
                            ->afterStateUpdated(function (FileUpload $component): void {
                                // Store the upload right away (FileUpload normally only does so on
                                // submit). Saving re-fires this hook with the stored path.
                                if (collect((array) $component->getRawState())->contains(fn ($file): bool => $file instanceof TemporaryUploadedFile)) {
                                    $component->saveUploadedFiles();

                                    return;
                                }

                                $this->analyzeUpload();
                            }),
                    ]),
                Section::make(__('filament-import-wizard::import.map.heading'))
                    ->description(__('filament-import-wizard::import.map.description'))
                    ->visible(fn (): bool => $this->step === self::STEP_MAP)
                    ->schema(fn (): array => array_map(
                        fn (ImportColumn $column): Select => $column->getSelect()
                            ->statePath("columnMap.{$column->getName()}")
                            ->options(array_combine($this->headers, $this->headers))
                            ->helperText(fn (mixed $state): ?string => $this->describeSample(is_string($state) ? $state : null)),
                        $this->getImportColumns(),
                    )),
                View::make('filament-import-wizard::steps.map-actions')
                    ->visible(fn (): bool => $this->step === self::STEP_MAP),
                View::make('filament-import-wizard::steps.review')
                    ->visible(fn (): bool => $this->step === self::STEP_REVIEW),
                View::make('filament-import-wizard::steps.import')
                    ->visible(fn (): bool => $this->step === self::STEP_IMPORT),
            ]);
    }

    /**
     * Step 1 → 2: read the header row and sample rows, and pre-fill the map.
     */
    public function analyzeUpload(): void
    {
        // FileUpload calls this right after it has stored the file, so the raw
        // state already holds the stored path. Reading it through getState()
        // would re-enter the upload's own save and recurse.
        $path = $this->data['file'] ?? null;
        $path = is_array($path) ? array_first($path) : $path;

        // The state is client-controllable: only accept paths inside our own
        // upload directory, never arbitrary files on the disk.
        if (! is_string($path) || ! str_starts_with($path, trim($this->getUploadDirectory(), '/').'/') || str_contains($path, '..')) {
            return;
        }

        $this->filePath = SpreadsheetConverter::supports($path)
            ? (new SpreadsheetConverter)->convert($this->getUploadDisk(), $path)
            : $path;

        $this->loadFile();
    }

    /**
     * Read the stored file's header row and sample rows, and pre-fill the map.
     */
    public function loadFile(): void
    {
        if ($this->filePath === '') {
            return;
        }

        $source = $this->source();

        $this->delimiter = $source->getDelimiter();
        $this->headers = array_values(array_filter($source->getHeaders(), 'filled'));
        $this->sample = iterator_to_array($source->read($source->getHeaders(), $source->getDataStartOffset(), 5), false);

        if ($this->headers === []) {
            $this->addError('data.file', __('filament-import-wizard::import.upload.empty'));

            return;
        }

        $this->data['columnMap'] = (new ColumnMapper)->guess($this->getImportColumns(), $this->headers);
        $this->step = self::STEP_MAP;
    }

    /**
     * Step 2 → 3: enforce required mappings, then begin the dry run.
     */
    public function confirmMapping(): void
    {
        $columnNames = array_map(fn (ImportColumn $column): string => $column->getName(), $this->getImportColumns());

        // Keep only known importer columns mapped to headers that really exist in the file.
        $map = array_filter(
            (array) ($this->data['columnMap'] ?? []),
            fn ($header, $name): bool => in_array($name, $columnNames, true) && in_array($header, $this->headers, true),
            ARRAY_FILTER_USE_BOTH,
        );

        foreach ($this->getImportColumns() as $column) {
            if ($column->isMappingRequired() && blank($map[$column->getName()] ?? null)) {
                $this->addError("data.columnMap.{$column->getName()}", __('filament-import-wizard::import.map.required', ['column' => $column->getLabel()]));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->data['columnMap'] = $map;
        $this->totalRows = $this->source()->countRows();
        $this->reviewOffset = $this->source()->getDataStartOffset();
        $this->reviewedRows = $this->invalidRows = 0;
        $this->errorRows = $this->errorsByColumn = [];
        $this->reviewFinished = false;
        $this->step = self::STEP_REVIEW;
    }

    /**
     * Validate the next slice of the file. Called by polling from the Review
     * step, so a file of any size is cross-checked a few thousand rows at a time.
     */
    #[Exposed]
    public function reviewNextSlice(): void
    {
        if ($this->step !== self::STEP_REVIEW || $this->reviewFinished) {
            return;
        }

        $source = $this->source();
        $validator = new RowValidator(static::getImporter(), $this->data['columnMap'], $this->getImporterOptions());
        $rows = $source->read($source->getHeaders(), $this->reviewOffset, $this->getRowsPerReviewRequest());
        $count = 0;

        foreach ($rows as $row) {
            $count++;
            $errors = $validator->validate($row);

            if ($errors === []) {
                continue;
            }

            $this->invalidRows++;

            foreach (array_keys($errors) as $column) {
                $this->errorsByColumn[$column] = ($this->errorsByColumn[$column] ?? 0) + 1;
            }

            if (count($this->errorRows) < $this->getMaxCollectedErrors()) {
                $this->errorRows[] = ['row' => $this->reviewedRows + $count, 'errors' => $errors];
            }
        }

        $this->reviewOffset = $rows->getReturn();
        $this->reviewedRows += $count;
        $this->reviewFinished = $count < $this->getRowsPerReviewRequest() || $this->reviewedRows >= $this->totalRows;
    }

    /**
     * Step 3 → 4: hand the file to Filament's queued importer.
     */
    public function startImport(): void
    {
        if (! $this->reviewFinished || $this->importId) {
            return;
        }

        $import = (new ImportStarter)->start(
            source: $this->source(),
            importer: static::getImporter(),
            fileName: $this->fileName ?: basename($this->filePath),
            columnMap: $this->data['columnMap'],
            options: $this->getImporterOptions(),
            chunkSize: $this->getChunkSize(),
            user: Filament::auth()->user(),
            authGuard: Filament::getAuthGuard(),
        );

        $this->importId = $import->getKey();
        $this->step = self::STEP_IMPORT;
    }

    public function getImport(): ?Import
    {
        return $this->importId ? app(Import::class)::query()->find($this->importId) : null;
    }

    public function startOver(): void
    {
        $this->reset(['step', 'filePath', 'fileName', 'headers', 'sample', 'totalRows', 'reviewOffset', 'reviewedRows', 'invalidRows', 'reviewFinished', 'errorRows', 'errorsByColumn', 'importId']);
        $this->data = [];
        $this->content->fill();

        Notification::make()->title(__('filament-import-wizard::import.started_over'))->send();
    }

    public function describeSample(?string $header): ?string
    {
        if (blank($header)) {
            return null;
        }

        $values = collect($this->sample)->pluck($header)->filter(fn ($value): bool => filled($value))->take(3);

        return $values->isEmpty() ? null : __('filament-import-wizard::import.map.sample', ['values' => $values->implode(', ')]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getImporterOptions(): array
    {
        return collect($this->data)->except(['file', 'columnMap'])->all();
    }

    protected function source(): CsvSource
    {
        return new CsvSource($this->getUploadDisk(), $this->filePath, $this->delimiter ?: null);
    }

    public function mount(): void
    {
        $this->content->fill();
    }
}
