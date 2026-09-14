<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Work still to be done: the order blocks a new one for the same plan and asset.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::InProgress], strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [self::Open->value, self::InProgress->value];
    }
}
