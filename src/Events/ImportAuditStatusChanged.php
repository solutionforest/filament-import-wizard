<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Events;

use Illuminate\Foundation\Events\Dispatchable;
use SolutionForest\FilamentImportWizard\Models\AuditStatus;
use SolutionForest\FilamentImportWizard\Models\ImportAudit;

/**
 * Dispatched on every audit transition, so an application can forward it to
 * its own activity log, SIEM or chat notification.
 */
class ImportAuditStatusChanged
{
    use Dispatchable;

    public function __construct(
        public ImportAudit $audit,
        public ?AuditStatus $from,
        public AuditStatus $to,
        public int|string|null $actorId,
        public ?string $note,
    ) {}
}
