<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderResult: string implements HasColor, HasLabel
{
    case Ok = 'ok';
    case NeedsFollowUp = 'needs_follow_up';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::NeedsFollowUp => 'Needs Follow-up',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::NeedsFollowUp => 'danger',
        };
    }
}
