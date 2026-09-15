<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetAuditStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'Counting',
            self::UnderReview => 'Under Review',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'info',
            self::UnderReview => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::InProgress, self::UnderReview], strict: true);
    }
}
