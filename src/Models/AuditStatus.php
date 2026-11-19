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
        $label = __('filament-import-wizard::audit.status.'.$this->value);

        return is_string($label) ? $label : $this->value;
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
