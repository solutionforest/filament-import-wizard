<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use SolutionForest\FilamentImportWizard\Http\ChunkedUploadController;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use SolutionForest\FilamentImportWizard\Pages\ImportAuditsPage;
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

    protected bool $chunkedUploads = false;

    protected bool $audit = false;

    /** @var bool|Closure(ImportAudit): bool */
    protected bool|Closure $requireApproval = false;

    /** @var (Closure(Authenticatable, ImportAudit): bool)|null */
    protected ?Closure $canApproveUsing = null;

    /** @var (Closure(Authenticatable): bool)|null */
    protected ?Closure $canViewAllUsing = null;

    protected bool $allowSelfApproval = false;

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

        if ($this->audit) {
            $panel->pages([ImportAuditsPage::class]);
        }

        if ($this->chunkedUploads) {
            $panel->authenticatedRoutes(function (): void {
                Route::prefix('import-wizard/upload')->name('import-wizard.upload.')->group(function (): void {
                    Route::post('start', [ChunkedUploadController::class, 'start'])->name('start');
                    Route::post('chunk', [ChunkedUploadController::class, 'chunk'])->name('chunk');
                    Route::post('complete', [ChunkedUploadController::class, 'complete'])->name('complete');
                });
            });
        }
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

    /**
     * Opt in to the audit trail: who imported what (file checksum, mapping,
     * review result, outcome) in `import_wizard_audits`, with an immutable event
     * trail and an "Import audit" page. Off by default; {@see requireApproval()}
     * switches it on because approval needs a record to approve.
     */
    public function audit(bool $enabled = true): static
    {
        $this->audit = $enabled;

        return $this;
    }

    /**
     * Hold imports for a second person's approval after Review. Pass a closure
     * to decide per import, e.g. `fn (ImportAudit $audit) => $audit->total_rows > 1000`.
     *
     * @param  bool|Closure(ImportAudit): bool  $condition
     */
    public function requireApproval(bool|Closure $condition = true): static
    {
        $this->requireApproval = $condition;
        $this->audit = $this->audit || $condition !== false;

        return $this;
    }

    /**
     * @param  Closure(Authenticatable, ImportAudit): bool  $callback
     */
    public function canApproveUsing(Closure $callback): static
    {
        $this->canApproveUsing = $callback;

        return $this;
    }

    /**
     * @param  Closure(Authenticatable): bool  $callback
     */
    public function canViewAllAuditsUsing(Closure $callback): static
    {
        $this->canViewAllUsing = $callback;

        return $this;
    }

    public function allowSelfApproval(bool $allow = true): static
    {
        $this->allowSelfApproval = $allow;

        return $this;
    }

    public function isAuditEnabled(): bool
    {
        return $this->audit;
    }

    public function requiresApproval(ImportAudit $audit): bool
    {
        return $this->audit && ($this->requireApproval instanceof Closure ? (bool) ($this->requireApproval)($audit) : $this->requireApproval);
    }

    public function allowsSelfApproval(): bool
    {
        return $this->allowSelfApproval;
    }

    /**
     * Default: the `approve-imports` Gate ability when the app defines it,
     * otherwise any panel user (other than the uploader, see allowSelfApproval()).
     */
    public function userCanApprove(Authenticatable $user, ImportAudit $audit): bool
    {
        if ($this->canApproveUsing instanceof Closure) {
            return (bool) ($this->canApproveUsing)($user, $audit);
        }

        return ! Gate::has('approve-imports') || Gate::forUser($user)->allows('approve-imports', $audit);
    }

    /**
     * Default: the `view-all-import-audits` Gate ability when defined,
     * otherwise everyone with panel access sees every audit.
     */
    public function userCanViewAll(Authenticatable $user): bool
    {
        if ($this->canViewAllUsing instanceof Closure) {
            return (bool) ($this->canViewAllUsing)($user);
        }

        return ! Gate::has('view-all-import-audits') || Gate::forUser($user)->allows('view-all-import-audits');
    }

    /**
     * Upload through small resumable chunks written straight to the disk, so
     * file size is no longer capped by PHP's `upload_max_filesize` /
     * `post_max_size`, and (with a shared disk) any server can take any chunk.
     */
    public function chunkedUploads(bool $enabled = true): static
    {
        $this->chunkedUploads = $enabled;

        return $this;
    }

    public function usesChunkedUploads(): bool
    {
        return $this->chunkedUploads;
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
