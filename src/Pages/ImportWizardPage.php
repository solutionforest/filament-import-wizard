<?php

declare(strict_types=1);

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
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use RuntimeException;
use SolutionForest\FilamentImportWizard\Checks\RowCheck;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Support\ColumnMapper;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\FileNormalizer;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;
use SolutionForest\FilamentImportWizard\Support\RowValidator;

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
 * ImportDispatcher, one job per slice.
 *
 * @property-read Schema $content
 */
abstract class ImportWizardPage extends Page
{
    public const STEP_UPLOAD = 1;

    public const STEP_MAP = 2;

    public const STEP_REVIEW = 3;

    public const STEP_IMPORT = 4;

    /**
     * @var class-string<Importer>|null
     */
    protected static ?string $importer = null;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-import-wizard::pages.import-wizard';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Locked]
    public int $step = self::STEP_UPLOAD;

    /** The stored upload, exactly as the user sent it. */
    #[Locked]
    public string $uploadPath = '';

    /** The UTF-8 CSV the wizard actually reads (equal to $uploadPath for plain UTF-8 CSV). */
    #[Locked]
    public string $filePath = '';

    #[Locked]
    public string $fileName = '';

    #[Locked]
    public string $delimiter = ',';

    /** @var list<string> */
    #[Locked]
    public array $sheets = [];

    /** @var list<string> */
    #[Locked]
    public array $headers = [];

    /** @var list<array<string, string|null>> */
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

    #[Locked]
    public string $reviewToken = '';

    /** @var list<array{row: int, errors: array<string, list<string>>}> */
    #[Locked]
    public array $errorRows = [];

    /** @var array<string, int> */
    #[Locked]
    public array $errorsByColumn = [];

    #[Locked]
    public ?int $importId = null;

    /**
     * @return class-string<Importer>
     */
    public static function getImporter(): string
    {
        return static::$importer ?? throw new LogicException(static::class.' must define the $importer property.');
    }

    public function getTitle(): string
    {
        return __('filament-import-wizard::import.title', [
            'label' => Str::of(class_basename(static::getImporter()))->beforeLast('Importer')->headline()->lower()->plural()->toString(),
        ]);
    }

    /**
     * @return list<ImportColumn>
     */
    public function getImportColumns(): array
    {
        return array_values(static::getImporter()::getColumns());
    }

    /**
     * File-level checks that run next to the importer's own rules during
     * Review, for example `new UniqueInFile(['email'])`.
     *
     * @return list<RowCheck>
     */
    protected function getRowChecks(): array
    {
        return [];
    }

    protected function getPlugin(): ImportWizardPlugin
    {
        return ImportWizardPlugin::current();
    }

    protected function getUploadDisk(): string
    {
        return $this->getPlugin()->getDisk();
    }

    protected function getUploadDirectory(): string
    {
        return $this->getPlugin()->getDirectory();
    }

    protected function getChunkSize(): int
    {
        return $this->getPlugin()->getChunkSize();
    }

    protected function getRowsPerReviewRequest(): int
    {
        return $this->getPlugin()->getRowsPerReviewRequest();
    }

    protected function getMaxCollectedErrors(): int
    {
        return $this->getPlugin()->getMaxCollectedErrors();
    }

    protected function getMaxUploadSizeInKilobytes(): ?int
    {
        return $this->getPlugin()->getMaxUploadSizeInKilobytes();
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
                            ->acceptedFileTypes($this->getAcceptedFileTypes())
                            ->maxSize($this->getMaxUploadSizeInKilobytes())
                            ->required()
                            ->afterStateUpdated(function (FileUpload $component): void {
                                // Store the upload right away (FileUpload normally only does so on
                                // submit). Saving re-fires this hook with the stored path.
                                if (collect((array) $component->getRawState())->contains(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile)) {
                                    $component->saveUploadedFiles();

                                    return;
                                }

                                $this->analyzeUpload();
                            }),
                        Select::make('sheet')
                            ->label(__('filament-import-wizard::import.upload.sheet'))
                            ->options(fn (): array => $this->sheets)
                            ->visible(fn (): bool => count($this->sheets) > 1 && $this->filePath === '')
                            ->live()
                            ->afterStateUpdated(fn (mixed $state) => $this->selectSheet($state)),
                    ]),
                Section::make(__('filament-import-wizard::import.map.heading'))
                    ->description(__('filament-import-wizard::import.map.description'))
                    ->visible(fn (): bool => $this->step === self::STEP_MAP)
                    ->schema(fn (): array => array_map(
                        fn (ImportColumn $column): Select => $column->getSelect()
                            ->statePath('columnMap.'.$column->getName())
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
     * @return list<string>
     */
    protected function getAcceptedFileTypes(): array
    {
        return [
            'text/csv', 'text/x-csv', 'application/csv', 'application/x-csv', 'text/comma-separated-values', 'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.spreadsheet',
        ];
    }

    /**
     * Step 1 → 2: called once the upload is stored. Spreadsheets with several
     * sheets wait for the user to pick one; everything else goes straight on.
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

        $this->uploadPath = $path;
        $this->fileName = basename($path);

        try {
            $this->sheets = (new FileNormalizer)->sheets($this->getUploadDisk(), $path);

            if (count($this->sheets) > 1) {
                return;
            }

            $this->prepareFile(0);
        } catch (RuntimeException $exception) {
            $this->addError('data.file', $exception->getMessage());
        }
    }

    public function selectSheet(mixed $index): void
    {
        $index = is_numeric($index) ? (int) $index : -1;

        if ($this->uploadPath === '' || ! isset($this->sheets[$index])) {
            return;
        }

        try {
            $this->prepareFile($index);
        } catch (RuntimeException $exception) {
            $this->addError('data.sheet', $exception->getMessage());
        }
    }

    protected function prepareFile(int $sheet): void
    {
        $this->filePath = (new FileNormalizer)->normalize($this->getUploadDisk(), $this->uploadPath, $sheet);

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
        $allHeaders = $source->getHeaders();
        $this->headers = array_values(array_filter($allHeaders, fn (string $header): bool => $header !== ''));
        $this->sample = iterator_to_array($source->read($allHeaders, $source->getDataStartOffset(), 5), false);

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
        $submitted = is_array($this->data['columnMap'] ?? null) ? $this->data['columnMap'] : [];

        // Keep only known importer columns mapped to headers that really exist in the file.
        $map = [];

        foreach ($submitted as $name => $header) {
            if (is_string($name) && is_string($header) && in_array($name, $columnNames, true) && in_array($header, $this->headers, true)) {
                $map[$name] = $header;
            }
        }

        $missing = false;

        foreach ($this->getImportColumns() as $column) {
            if ($column->isMappingRequired() && blank($map[$column->getName()] ?? null)) {
                $this->addError('data.columnMap.'.$column->getName(), __('filament-import-wizard::import.map.required', ['column' => $column->getLabel()]));
                $missing = true;
            }
        }

        if ($missing) {
            return;
        }

        $this->data['columnMap'] = $map;

        $source = $this->source();

        $this->totalRows = $source->countRows();
        $this->reviewOffset = $source->getDataStartOffset();
        $this->reviewedRows = $this->invalidRows = 0;
        $this->errorRows = $this->errorsByColumn = [];
        $this->reviewFinished = false;
        $this->reviewToken = Str::random(16);
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
        $validator = new RowValidator(
            static::getImporter(),
            $this->getColumnMap(),
            $this->getImporterOptions(),
            $this->getRowChecks(),
            $this->reviewToken,
            fresh: $this->reviewedRows === 0,
        );

        $perRequest = $this->getRowsPerReviewRequest();
        $rows = $source->read($source->getHeaders(), $this->reviewOffset, $perRequest);
        $count = 0;

        foreach ($rows as $row) {
            $count++;
            $rowNumber = $this->reviewedRows + $count;
            $errors = $validator->validate($row, $rowNumber);

            if ($errors === []) {
                continue;
            }

            $this->invalidRows++;

            foreach (array_keys($errors) as $column) {
                $this->errorsByColumn[$column] = ($this->errorsByColumn[$column] ?? 0) + 1;
            }

            if (count($this->errorRows) < $this->getMaxCollectedErrors()) {
                $this->errorRows[] = ['row' => $rowNumber, 'errors' => $errors];
            }
        }

        $this->reviewOffset = $rows->getReturn();
        $this->reviewedRows += $count;
        $this->reviewFinished = $count < $perRequest || $this->reviewedRows >= $this->totalRows;

        if ($this->reviewFinished) {
            $validator->finish($this->reviewToken);
        }
    }

    /**
     * Step 3 → 4: hand the file to Filament's queued importer.
     */
    public function startImport(): void
    {
        if (! $this->reviewFinished || $this->importId !== null) {
            return;
        }

        $import = (new ImportStarter)->start(
            source: $this->source(),
            importer: static::getImporter(),
            fileName: $this->fileName,
            columnMap: $this->getColumnMap(),
            options: $this->getImporterOptions(),
            chunkSize: $this->getChunkSize(),
            user: Filament::auth()->user(),
            authGuard: Filament::getAuthGuard(),
        );

        $key = $import->getKey();
        $this->importId = is_int($key) ? $key : null;
        $this->step = self::STEP_IMPORT;
    }

    public function getImport(): ?Import
    {
        if ($this->importId === null) {
            return null;
        }

        $import = app(Import::class)::query()->find($this->importId);

        return $import instanceof Import ? $import : null;
    }

    public function startOver(): void
    {
        $this->reset(['step', 'uploadPath', 'filePath', 'fileName', 'sheets', 'headers', 'sample', 'totalRows', 'reviewOffset', 'reviewedRows', 'invalidRows', 'reviewFinished', 'reviewToken', 'errorRows', 'errorsByColumn', 'importId']);
        $this->data = [];
        $this->content->fill();

        Notification::make()->title(__('filament-import-wizard::import.started_over'))->send();
    }

    public function describeSample(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        $values = collect($this->sample)
            ->pluck($header)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->take(3);

        if ($values->isEmpty()) {
            return null;
        }

        $text = __('filament-import-wizard::import.map.sample', ['values' => implode(', ', $values->all())]);

        return is_string($text) ? $text : null;
    }

    /**
     * @return array<string, string>
     */
    protected function getColumnMap(): array
    {
        $map = [];

        foreach (is_array($this->data['columnMap'] ?? null) ? $this->data['columnMap'] : [] as $name => $header) {
            if (is_string($name) && is_string($header)) {
                $map[$name] = $header;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getImporterOptions(): array
    {
        return collect($this->data)->except(['file', 'columnMap', 'sheet'])->all();
    }

    protected function source(): CsvSource
    {
        return new CsvSource($this->getUploadDisk(), $this->filePath, $this->delimiter);
    }

    public function mount(): void
    {
        $this->content->fill();
    }
}
