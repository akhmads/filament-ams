<?php

namespace App\Exceptions;

use App\Enums\AssetAuditStatus;
use App\Enums\AuditFollowUp;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Models\Location;
use Exception;

class AssetAuditException extends Exception
{
    public static function notInStatus(AssetAudit $audit, string $action, AssetAuditStatus ...$allowed): self
    {
        $allowedLabels = collect($allowed)->map(fn (AssetAuditStatus $status): string => $status->getLabel())->join(', ', ' or ');

        return new self("Audit {$audit->number} is {$audit->status->getLabel()}; only an audit that is {$allowedLabels} can be {$action}.");
    }

    public static function unknownCode(string $code): self
    {
        return new self("No asset has the code {$code}.");
    }

    public static function disposedAsset(Asset $asset): self
    {
        return new self("Asset {$asset->code} was disposed of and is no longer on the register. Report it to the asset manager.");
    }

    public static function notARoom(Location $location): self
    {
        return new self("{$location->name} is not a room or warehouse. Choose where the asset actually stands.");
    }

    public static function followUpNotApplicable(AssetAuditLine $line, AuditFollowUp $followUp): self
    {
        $code = $line->asset?->code ?? "line {$line->id}";

        return new self("\"{$followUp->getLabel()}\" does not fit the finding for {$code}.");
    }

    public static function movedSinceAudit(AssetAudit $audit, Asset $asset): self
    {
        return new self("Asset {$asset->code} has been moved since audit {$audit->number} looked at it. Clear its corrections, or check it again.");
    }

    public static function cannotAdjust(AssetAudit $audit, AssetTransitionException $previous): self
    {
        return new self("Audit {$audit->number}: {$previous->getMessage()}", previous: $previous);
    }
}
