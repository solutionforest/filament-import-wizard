<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Models;

use Filament\Actions\Imports\Importer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One row per wizard run: who uploaded which file (with checksum), what the
 * Review found, who approved it, and how the import ended.
 *
 * @property int $id
 * @property string|null $panel_id
 * @property int|null $user_id
 * @property class-string<Importer> $importer
 * @property string $file_name
 * @property string $disk
 * @property string $upload_path
 * @property string $file_path
 * @property string $delimiter
 * @property string|null $checksum
 * @property AuditStatus $status
 * @property int $total_rows
 * @property int $invalid_rows
 * @property array<string, string>|null $column_map
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $error_summary
 * @property int|null $import_id
 * @property int $successful_rows
 * @property int $failed_rows
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property string|null $ip_address
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 */
class ImportAudit extends Model
{
    protected $table = 'import_wizard_audits';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AuditStatus::class,
            'column_map' => 'array',
            'options' => 'array',
            'error_summary' => 'array',
            'decided_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ImportAuditEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ImportAuditEvent::class, 'audit_id')->orderBy('id');
    }

    /**
     * @return BelongsTo<covariant Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(app(Authenticatable::class)::class, 'user_id');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === AuditStatus::PendingApproval;
    }
}
