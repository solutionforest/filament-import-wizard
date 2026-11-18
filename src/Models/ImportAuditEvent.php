<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One state change of an {@see ImportAudit}: the immutable trail.
 *
 * @property int $id
 * @property int $audit_id
 * @property int|null $user_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class ImportAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'import_wizard_audit_events';

    protected $guarded = [];

    /**
     * @return BelongsTo<ImportAudit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(ImportAudit::class, 'audit_id');
    }
}
