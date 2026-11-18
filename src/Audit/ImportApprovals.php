<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Audit;

use Filament\Actions\Imports\Importer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use SolutionForest\FilamentImportWizard\ImportWizardPlugin;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use SolutionForest\FilamentImportWizard\Support\CsvSource;
use SolutionForest\FilamentImportWizard\Support\ImportStarter;

/**
 * The maker–checker step: an approver releases (or rejects) an import that
 * another user reviewed. The import runs as the original uploader, so their
 * completion notification and failed-rows download stay theirs.
 */
class ImportApprovals
{
    public function __construct(protected AuditRecorder $recorder) {}

    public function approve(ImportAudit $audit, Authenticatable $approver, ?string $note = null): void
    {
        $this->authorize($audit, $approver);

        $columnMap = $audit->column_map;

        if ($columnMap === null) {
            throw new RuntimeException("Audit #{$audit->id} has no reviewed column map.");
        }

        $importer = $audit->importer;

        if (! is_subclass_of($importer, Importer::class)) {
            throw new RuntimeException("[{$importer}] is not a Filament importer.");
        }

        $plugin = ImportWizardPlugin::current();

        $this->recorder->approved($audit, $approver, $note);

        $uploader = $this->uploader($audit);

        (new ImportStarter)->start(
            source: new CsvSource($audit->disk, $audit->file_path, $audit->delimiter),
            importer: $importer,
            fileName: $audit->file_name,
            columnMap: $columnMap,
            options: $audit->options ?? [],
            chunkSize: $plugin->getChunkSize(),
            user: $uploader,
            onCreated: fn ($import) => $this->recorder->started($audit, $import, $approver),
        );
    }

    public function reject(ImportAudit $audit, Authenticatable $approver, string $note): void
    {
        $this->authorize($audit, $approver);

        $this->recorder->rejected($audit, $approver, $note);
    }

    public function canDecide(ImportAudit $audit, Authenticatable $user): bool
    {
        if (! $audit->isPendingApproval()) {
            return false;
        }

        $plugin = ImportWizardPlugin::current();

        $approverId = $user->getAuthIdentifier();

        if (! $plugin->allowsSelfApproval() && $audit->user_id !== null && is_scalar($approverId) && (string) $audit->user_id === (string) $approverId) {
            return false;
        }

        return $plugin->userCanApprove($user, $audit);
    }

    protected function authorize(ImportAudit $audit, Authenticatable $user): void
    {
        if (! $this->canDecide($audit, $user)) {
            throw new AuthorizationException('You cannot approve or reject this import.');
        }
    }

    protected function uploader(ImportAudit $audit): ?Authenticatable
    {
        if ($audit->user_id === null) {
            return null;
        }

        $user = app(Authenticatable::class)->newQuery()->find($audit->user_id);

        return $user instanceof Authenticatable ? $user : null;
    }
}
