<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Audit;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SolutionForest\FilamentImportWizard\Events\ImportAuditStatusChanged;
use SolutionForest\FilamentImportWizard\Models\AuditStatus;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;
use SolutionForest\FilamentImportWizard\Models\ImportAuditEvent;

/**
 * The only writer of {@see ImportAudit}: every change goes through
 * {@see transition()}, which also appends the immutable event trail and fires
 * {@see ImportAuditStatusChanged}.
 */
class AuditRecorder
{
    /**
     * @param  class-string<Importer>  $importer
     */
    public function uploaded(
        ?string $panelId,
        ?Authenticatable $user,
        string $importer,
        string $fileName,
        string $disk,
        string $uploadPath,
        string $filePath,
        string $delimiter,
        ?string $ipAddress,
    ): ImportAudit {
        $audit = ImportAudit::query()->create([
            'panel_id' => $panelId,
            'user_id' => $this->id($user),
            'importer' => $importer,
            'file_name' => $fileName,
            'disk' => $disk,
            'upload_path' => $uploadPath,
            'file_path' => $filePath,
            'delimiter' => $delimiter,
            'checksum' => $this->checksum($disk, $uploadPath),
            'status' => AuditStatus::Uploaded,
            'ip_address' => $ipAddress,
        ]);

        $this->log($audit, null, AuditStatus::Uploaded, $user);

        return $audit;
    }

    /**
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $errorSummary
     */
    public function reviewed(ImportAudit $audit, array $columnMap, array $options, int $totalRows, int $invalidRows, array $errorSummary): void
    {
        $audit->forceFill([
            'column_map' => $columnMap,
            'options' => $options,
            'total_rows' => $totalRows,
            'invalid_rows' => $invalidRows,
            'error_summary' => $errorSummary,
        ]);

        $this->transition($audit, AuditStatus::Reviewed, null, "{$invalidRows} of {$totalRows} rows have problems");
    }

    public function submitted(ImportAudit $audit, ?Authenticatable $user): void
    {
        $this->transition($audit, AuditStatus::PendingApproval, $user);
    }

    public function rejected(ImportAudit $audit, Authenticatable $approver, ?string $note): void
    {
        $audit->forceFill(['decided_by' => $this->id($approver), 'decided_at' => now(), 'decision_note' => $note]);

        $this->transition($audit, AuditStatus::Rejected, $approver, $note);
    }

    public function approved(ImportAudit $audit, Authenticatable $approver, ?string $note): void
    {
        $audit->forceFill(['decided_by' => $this->id($approver), 'decided_at' => now(), 'decision_note' => $note]);

        $this->transition($audit, AuditStatus::Approved, $approver, $note);
    }

    public function started(ImportAudit $audit, Import $import, ?Authenticatable $user): void
    {
        $key = $import->getKey();
        $audit->forceFill(['import_id' => is_int($key) ? $key : null, 'started_at' => now()]);

        $this->transition($audit, AuditStatus::Importing, $user);
    }

    public function completed(ImportAudit $audit, Import $import): void
    {
        $failed = $import->getFailedRowsCount();

        $audit->forceFill([
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $failed,
            'completed_at' => now(),
        ]);

        $this->transition($audit, $failed > 0 ? AuditStatus::CompletedWithFailures : AuditStatus::Completed, null);
    }

    public function transition(ImportAudit $audit, AuditStatus $to, ?Authenticatable $actor, ?string $note = null): void
    {
        $from = $audit->status;

        if ($from->isFinal()) {
            throw new RuntimeException(__('filament-import-wizard::audit.errors.already_final', ['id' => $audit->id, 'status' => $from->getLabel()]));
        }

        $audit->status = $to;
        $audit->save();

        $this->log($audit, $from, $to, $actor, $note);
    }

    protected function log(ImportAudit $audit, ?AuditStatus $from, AuditStatus $to, ?Authenticatable $actor, ?string $note = null): void
    {
        ImportAuditEvent::query()->create([
            'audit_id' => $audit->id,
            'user_id' => $this->id($actor),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
        ]);

        ImportAuditStatusChanged::dispatch($audit, $from, $to, $this->id($actor), $note);
    }

    protected function id(?Authenticatable $user): int|string|null
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * SHA-256 of the file exactly as uploaded, streamed so size does not matter.
     */
    protected function checksum(string $disk, string $path): ?string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
