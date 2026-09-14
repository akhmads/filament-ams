<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasColor, HasLabel
{
    case Initial = 'initial';
    case Assignment = 'assignment';
    case Return_ = 'return';
    case Transfer = 'transfer';
    case Maintenance = 'maintenance';
    case Repair = 'repair';
    case Disposal = 'disposal';
    case AuditAdjustment = 'audit_adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Initial => 'Initial Record',
            self::Assignment => 'Handover',
            self::Return_ => 'Return',
            self::Transfer => 'Transfer',
            self::Maintenance => 'Maintenance',
            self::Repair => 'Repair',
            self::Disposal => 'Disposal',
            self::AuditAdjustment => 'Audit Adjustment',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Initial => 'gray',
            self::Assignment => 'info',
            self::Return_ => 'success',
            self::Transfer => 'primary',
            self::Maintenance, self::Repair => 'warning',
            self::Disposal => 'danger',
            self::AuditAdjustment => 'gray',
        };
    }
}
