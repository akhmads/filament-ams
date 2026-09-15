<?php

namespace App\Exceptions;

use App\Enums\AssetDisposalStatus;
use App\Enums\DepreciationBook;
use App\Enums\DisposalMethod;
use App\Models\Asset;
use App\Models\AssetDisposal;
use Carbon\CarbonImmutable;
use Exception;

class AssetDisposalException extends Exception
{
    public static function notInStatus(AssetDisposal $disposal, string $action, AssetDisposalStatus ...$allowed): self
    {
        $allowedLabels = collect($allowed)->map(fn (AssetDisposalStatus $status): string => $status->getLabel())->join(', ', ' or ');

        return new self("Disposal {$disposal->number} is {$disposal->status->getLabel()}; only disposals that are {$allowedLabels} can be {$action}.");
    }

    public static function noLines(AssetDisposal $disposal): self
    {
        return new self("Disposal {$disposal->number} does not list any assets.");
    }

    public static function notDisposable(Asset $asset): self
    {
        return new self("Asset {$asset->code} is {$asset->status->getLabel()}. Only an asset that is available, in storage, retired or lost can be written off.");
    }

    public static function stillHeld(Asset $asset): self
    {
        return new self("Asset {$asset->code} is still held by an employee and must be returned before it is written off.");
    }

    public static function alreadyProposed(Asset $asset, AssetDisposal $other): self
    {
        return new self("Asset {$asset->code} is already on disposal {$other->number}. Finish or cancel that one first.");
    }

    public static function dateInFuture(AssetDisposal $disposal): self
    {
        return new self("Disposal {$disposal->number} is dated {$disposal->disposal_date->format('d M Y')}; it can be completed on or after that day.");
    }

    public static function unexpectedProceeds(Asset $asset, DisposalMethod $method): self
    {
        return new self("Asset {$asset->code} is {$method->getLabel()}; only a sale or trade-in can bring in proceeds.");
    }

    public static function depreciationNotPosted(Asset $asset, DepreciationBook $book, CarbonImmutable $throughMonth): self
    {
        return new self("Post {$book->getLabel()} depreciation through {$throughMonth->format('F Y')} before writing off {$asset->code}, so its book value is final.");
    }

    public static function depreciatedInDisposalMonth(Asset $asset, DepreciationBook $book, CarbonImmutable $month): self
    {
        return new self("{$book->getLabel()} depreciation for {$asset->code} is already posted for {$month->format('F Y')} or later. A disposal must be dated after the last month the asset was depreciated.");
    }

    public static function assetCannotMove(AssetDisposal $disposal, AssetTransitionException $previous): self
    {
        return new self("Disposal {$disposal->number}: {$previous->getMessage()}", previous: $previous);
    }
}
