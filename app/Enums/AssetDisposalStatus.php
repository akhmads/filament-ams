<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetDisposalStatus: string implements HasColor, HasLabel
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proposed => 'Waiting for Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Proposed => 'warning',
            self::Approved => 'primary',
            self::Completed => 'success',
            self::Rejected, self::Cancelled => 'gray',
        };
    }

    /**
     * Still waiting on a decision or on being carried out. An asset can be on at
     * most one open disposal.
     */
    public function isOpen(): bool
    {
        return in_array($this->value, self::openValues(), strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [self::Proposed->value, self::Approved->value];
    }
}
