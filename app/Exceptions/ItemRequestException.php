<?php

namespace App\Exceptions;

use App\Enums\ItemRequestStatus;
use App\Models\ItemRequest;
use Exception;

class ItemRequestException extends Exception
{
    public static function notInStatus(ItemRequest $request, string $action, ItemRequestStatus ...$allowed): self
    {
        $allowedLabels = collect($allowed)->map(fn (ItemRequestStatus $status): string => $status->getLabel())->join(', ', ' or ');

        return new self("Item request {$request->number} is {$request->status->getLabel()}; only requests that are {$allowedLabels} can be {$action}.");
    }

    public static function noLines(ItemRequest $request): self
    {
        return new self("Item request {$request->number} does not list any items.");
    }

    public static function cannotIssue(ItemRequest $request, StockException $previous): self
    {
        return new self("Item request {$request->number}: {$previous->getMessage()}", previous: $previous);
    }
}
