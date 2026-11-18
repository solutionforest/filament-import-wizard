<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use SolutionForest\FilamentImportWizard\Pages\ImportWizardPage;

/**
 * Registers import wizard pages on a panel and holds that panel's settings, so
 * every panel of an app can use its own disk, directory and limits:
 *
 *     $panel->plugin(
 *         ImportWizardPlugin::make()
 *             ->pages([ImportContacts::class])
 *             ->disk('s3')
 *             ->chunkSize(1000),
 *     );
 *
 * Pages registered directly with `->pages()` still work and use the defaults.
 */
final class ImportWizardPlugin implements Plugin
{
    /** @var list<class-string<ImportWizardPage>> */
    protected array $pages = [];

    protected ?string $disk = null;

    protected string $directory = 'import-wizard';

    protected int $chunkSize = 500;

    protected int $rowsPerReviewRequest = 2000;

    protected int $maxCollectedErrors = 50;

    protected ?int $maxUploadSizeInKilobytes = null;

    public static function make(): static
    {
        return app(self::class);
    }

    /**
     * The plugin of the current panel, or a default instance when the panel
     * did not register one.
     */
    public static function current(): self
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel !== null && $panel->hasPlugin('import-wizard')) {
            $plugin = $panel->getPlugin('import-wizard');

            if ($plugin instanceof self) {
                return $plugin;
            }
        }

        return new self;
    }

    public function getId(): string
    {
        return 'import-wizard';
    }

    public function register(Panel $panel): void
    {
        $panel->pages($this->pages);
    }

    public function boot(Panel $panel): void {}

    /**
     * @param  list<class-string<ImportWizardPage>>  $pages
     */
    public function pages(array $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    public function disk(?string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directory = trim($directory, '/');

        return $this;
    }

    public function chunkSize(int $size): static
    {
        $this->chunkSize = max(1, $size);

        return $this;
    }

    public function rowsPerReviewRequest(int $rows): static
    {
        $this->rowsPerReviewRequest = max(1, $rows);

        return $this;
    }

    public function maxCollectedErrors(int $count): static
    {
        $this->maxCollectedErrors = max(1, $count);

        return $this;
    }

    public function maxUploadSize(?int $kilobytes): static
    {
        $this->maxUploadSizeInKilobytes = $kilobytes;

        return $this;
    }

    public function getDisk(): string
    {
        $default = config('filament.default_filesystem_disk', 'local');

        return $this->disk ?? (is_string($default) ? $default : 'local');
    }

    /**
     * Uploads are kept per panel so two panels never see each other's files.
     */
    public function getDirectory(?string $panelId = null): string
    {
        $panelId ??= Filament::getCurrentOrDefaultPanel()?->getId();

        return $panelId === null ? $this->directory : $this->directory.'/'.$panelId;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getRowsPerReviewRequest(): int
    {
        return $this->rowsPerReviewRequest;
    }

    public function getMaxCollectedErrors(): int
    {
        return $this->maxCollectedErrors;
    }

    public function getMaxUploadSizeInKilobytes(): ?int
    {
        return $this->maxUploadSizeInKilobytes;
    }
}
