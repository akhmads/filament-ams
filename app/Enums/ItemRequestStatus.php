<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItemRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Waiting for Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Fulfilled => 'Issued',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Approved => 'primary',
            self::Fulfilled => 'success',
            self::Rejected, self::Cancelled => 'gray',
        };
    }

    /**
     * Still waiting on a decision or on the goods.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::Approved], strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [self::Submitted->value, self::Approved->value];
    }
}
