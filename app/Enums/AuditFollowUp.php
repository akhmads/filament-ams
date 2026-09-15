<?php

namespace App\Enums;

use App\Models\AssetAuditLine;
use Filament\Support\Contracts\HasLabel;

/**
 * A correction a reviewer can choose for an audit finding. The case value is the
 * line's column that holds the choice.
 */
enum AuditFollowUp: string implements HasLabel
{
    case Relocate = 'apply_relocation';
    case UpdateCondition = 'apply_condition';
    case MarkLost = 'mark_lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::Relocate => 'Move to where it was found',
            self::UpdateCondition => 'Update its condition',
            self::MarkLost => 'Mark as lost',
        };
    }

    public function isApplicableTo(AssetAuditLine $line): bool
    {
        return match ($this) {
            self::Relocate => $line->result === AuditResult::Misplaced,
            self::UpdateCondition => $line->hasConditionChanged(),
            self::MarkLost => $line->result === AuditResult::Missing,
        };
    }
}
