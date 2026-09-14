<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssignmentType: string implements HasColor, HasLabel
{
    case Checkout = 'checkout';
    case Checkin = 'checkin';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Checkout => 'Handover',
            self::Checkin => 'Return',
            self::Transfer => 'Transfer',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Checkout => 'info',
            self::Checkin => 'success',
            self::Transfer => 'primary',
        };
    }

    public function toMovementType(): MovementType
    {
        return match ($this) {
            self::Checkout => MovementType::Assignment,
            self::Checkin => MovementType::Return_,
            self::Transfer => MovementType::Transfer,
        };
    }

    /**
     * Wording for printed documents, which stay in Indonesian because they are
     * signed and filed on paper in Indonesia — kept apart from the interface
     * labels, which follow the application language.
     */
    public function printedLabel(): string
    {
        return match ($this) {
            self::Checkout => 'Serah Terima',
            self::Checkin => 'Pengembalian',
            self::Transfer => 'Mutasi',
        };
    }
}
