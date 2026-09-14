<?php

namespace App\Exceptions;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Employee;
use Exception;

class AssetTransitionException extends Exception
{
    public static function terminal(Asset $asset): self
    {
        return new self("Asset {$asset->code} is {$asset->status->getLabel()} and can no longer be moved.");
    }

    public static function invalidStatus(Asset $asset, AssetStatus $target): self
    {
        return new self("Asset {$asset->code} cannot move from {$asset->status->getLabel()} to {$target->getLabel()}.");
    }

    public static function alreadyHeld(Asset $asset): self
    {
        // Looked up directly rather than through the relation: the message is
        // built on an error path where the relation is rarely already loaded.
        $holder = Employee::query()->whereKey($asset->current_employee_id)->value('name') ?? 'someone else';

        return new self("Asset {$asset->code} is still held by {$holder} and must be returned first.");
    }

    public static function missingTarget(string $message): self
    {
        return new self($message);
    }
}
