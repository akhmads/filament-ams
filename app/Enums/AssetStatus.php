<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasColor, HasIcon, HasLabel
{
    case Available = 'available';
    case InUse = 'in_use';
    case InStorage = 'in_storage';
    case InTransit = 'in_transit';
    case UnderMaintenance = 'under_maintenance';
    case UnderRepair = 'under_repair';
    case Lost = 'lost';
    case Retired = 'retired';
    case Disposed = 'disposed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::InUse => 'In Use',
            self::InStorage => 'In Storage',
            self::InTransit => 'In Transit',
            self::UnderMaintenance => 'Under Maintenance',
            self::UnderRepair => 'Under Repair',
            self::Lost => 'Lost',
            self::Retired => 'Retired',
            self::Disposed => 'Disposed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::InUse => 'info',
            self::InStorage, self::Retired => 'gray',
            self::InTransit, self::UnderMaintenance => 'warning',
            self::UnderRepair, self::Lost => 'danger',
            self::Disposed => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::InUse => 'heroicon-o-user',
            self::InStorage => 'heroicon-o-building-storefront',
            self::InTransit => 'heroicon-o-truck',
            self::UnderMaintenance => 'heroicon-o-wrench-screwdriver',
            self::UnderRepair => 'heroicon-o-wrench',
            self::Lost => 'heroicon-o-question-mark-circle',
            self::Retired => 'heroicon-o-archive-box',
            self::Disposed => 'heroicon-o-trash',
        };
    }

    /**
     * A terminal status: the asset can no longer be moved or handed over.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Disposed, self::Lost], strict: true);
    }

    /**
     * An asset may only be handed over from these statuses.
     */
    public function isAssignable(): bool
    {
        return in_array($this, [self::Available, self::InStorage], strict: true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Available => [self::InUse, self::InStorage, self::InTransit, self::UnderMaintenance, self::UnderRepair, self::Lost, self::Retired, self::Disposed],
            self::InUse => [self::Available, self::InStorage, self::InTransit, self::UnderMaintenance, self::UnderRepair, self::Lost],
            self::InStorage => [self::Available, self::InUse, self::InTransit, self::UnderMaintenance, self::UnderRepair, self::Lost, self::Retired, self::Disposed],
            self::InTransit => [self::Available, self::InUse, self::InStorage, self::Lost],
            self::UnderMaintenance, self::UnderRepair => [self::Available, self::InStorage, self::InUse, self::Retired],
            self::Retired => [self::Disposed, self::InStorage],
            self::Lost => [self::Available, self::Disposed],
            self::Disposed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $status === $this || in_array($status, $this->allowedTransitions(), strict: true);
    }

    /**
     * An asset may only be written off when it is out of use: idle, in store,
     * retired, or lost.
     */
    public function isDisposable(): bool
    {
        return in_array($this->value, self::disposableValues(), strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function disposableValues(): array
    {
        return [self::Available->value, self::InStorage->value, self::Retired->value, self::Lost->value];
    }
}
