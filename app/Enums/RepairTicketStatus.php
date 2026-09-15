<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RepairTicketStatus: string implements HasColor, HasLabel
{
    case Reported = 'reported';
    case Verified = 'verified';
    case Approved = 'approved';
    case InRepair = 'in_repair';
    case Repaired = 'repaired';
    case Unrepairable = 'unrepairable';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::Verified => 'Verified',
            self::Approved => 'Approved',
            self::InRepair => 'In Repair',
            self::Repaired => 'Repaired',
            self::Unrepairable => 'Cannot Be Repaired',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Reported => 'warning',
            self::Verified => 'info',
            self::Approved => 'primary',
            self::InRepair => 'danger',
            self::Repaired => 'success',
            self::Unrepairable, self::Rejected => 'gray',
        };
    }

    /**
     * Still being handled: not yet repaired, written off or rejected.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Reported, self::Verified, self::Approved, self::InRepair], strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [self::Reported->value, self::Verified->value, self::Approved->value, self::InRepair->value];
    }
}
