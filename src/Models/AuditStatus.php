<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Models;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditStatus: string implements HasColor, HasLabel
{
    case Uploaded = 'uploaded';
    case Reviewed = 'reviewed';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Importing = 'importing';
    case Completed = 'completed';
    case CompletedWithFailures = 'completed_with_failures';

    public function getLabel(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Reviewed => 'Reviewed',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Importing => 'Importing',
            self::Completed => 'Completed',
            self::CompletedWithFailures => 'Completed with failures',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Uploaded, self::Reviewed => 'gray',
            self::PendingApproval, self::CompletedWithFailures => 'warning',
            self::Rejected => 'danger',
            self::Importing, self::Approved => 'info',
            self::Completed => 'success',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Completed, self::CompletedWithFailures], true);
    }
}
