<?php

namespace App\Exceptions;

use App\Models\Location;
use App\Models\StockDocument;
use App\Models\StockItem;
use App\Support\Quantity;
use Exception;

class StockException extends Exception
{
    public static function alreadyPosted(StockDocument $document): self
    {
        return new self("Stock document {$document->number} is already posted. Correct it with an adjustment instead.");
    }

    public static function noLines(StockDocument $document): self
    {
        return new self("Stock document {$document->number} has no items yet.");
    }

    public static function notAWarehouse(StockDocument $document, ?Location $location): self
    {
        $name = $location?->name ?? 'The chosen location';

        return new self("Stock document {$document->number}: {$name} is not an active warehouse, and stock can only be kept in one.");
    }

    public static function sameWarehouse(StockDocument $document): self
    {
        return new self("Transfer {$document->number} sends stock to the warehouse it comes from. Choose another destination.");
    }

    public static function invalidLine(StockDocument $document, StockItem $item, string $problem): self
    {
        return new self("Stock document {$document->number}, {$item->code}: {$problem}");
    }

    public static function insufficient(StockItem $item, Location $location, int $availableHundredths, int $requestedHundredths): self
    {
        $available = Quantity::format(Quantity::toDecimal($availableHundredths));
        $requested = Quantity::format(Quantity::toDecimal($requestedHundredths));

        return new self("Only {$available} {$item->unit} of {$item->code} {$item->name} is in {$location->name}, but {$requested} is needed.");
    }

    public static function workNotInProgress(string $reference): self
    {
        return new self("Spare parts can only be used on {$reference} while the work is in progress.");
    }
}
