<?php

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Enums\StockMovementType;
use App\Exceptions\StockException;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock changes. Posting a document writes a stock movement per
 * warehouse it touches, then updates the cached balance under a row lock.
 *
 * Stock is valued at moving average cost per warehouse. The balance keeps the
 * total value rather than a unit cost, so goods leave at their share of that
 * value and the last unit out takes whatever remains — rounding never strands
 * value in an empty warehouse.
 */
class StockLedger
{
    public function __construct(
        private readonly StockNotifier $notifier,
    ) {}

    public function post(StockDocument $document, ?User $postedBy = null): StockDocument
    {
        return DB::transaction(function () use ($document, $postedBy): StockDocument {
            // Lock the document first, so two people posting it at once cannot both succeed.
            StockDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $document->refresh();

            if ($document->status !== StockDocumentStatus::Draft) {
                throw StockException::alreadyPosted($document);
            }

            /** @var Collection<int, StockDocumentLine> $lines */
            $lines = $document->lines()->with('stockItem')->orderBy('id')->get();

            if ($lines->isEmpty()) {
                throw StockException::noLines($document);
            }

            $warehouse = $this->warehouse($document, $document->location_id);
            $destination = $document->type === StockDocumentType::Transfer
                ? $this->warehouse($document, $document->destination_location_id)
                : null;

            if ($destination !== null && $destination->is($warehouse)) {
                throw StockException::sameWarehouse($document);
            }

            $onHandBefore = $this->totalsOnHand($lines);

            foreach ($lines as $line) {
                $this->postLine($document, $line, $warehouse, $destination, $postedBy);
            }

            $document->forceFill([
                'status' => StockDocumentStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $postedBy?->id,
            ])->save();

            $this->notifier->stockBelowMinimum($this->itemsThatFellBelowMinimum($lines, $onHandBefore));

            return $document;
        });
    }

    private function postLine(StockDocument $document, StockDocumentLine $line, Location $warehouse, ?Location $destination, ?User $postedBy): void
    {
        $item = $line->stockItem;
        $quantity = Quantity::toHundredths($line->quantity);

        match ($document->type) {
            StockDocumentType::Receipt => $this->postReceipt($document, $line, $item, $warehouse, $quantity, $postedBy),
            StockDocumentType::Issue => $this->postIssue($document, $line, $item, $warehouse, $quantity, $postedBy),
            StockDocumentType::Transfer => $this->postTransfer($document, $line, $item, $warehouse, $destination, $quantity, $postedBy),
            StockDocumentType::Adjustment => $this->postAdjustment($document, $line, $item, $warehouse, $quantity, $postedBy),
            StockDocumentType::Count => $this->postCount($document, $line, $item, $warehouse, $quantity, $postedBy),
        };
    }

    private function postReceipt(StockDocument $document, StockDocumentLine $line, StockItem $item, Location $warehouse, int $quantity, ?User $postedBy): void
    {
        if ($quantity <= 0) {
            throw StockException::invalidLine($document, $item, 'the quantity must be more than zero.');
        }

        if (! $item->is_active) {
            throw StockException::invalidLine($document, $item, 'the item is inactive and cannot be received.');
        }

        if ($line->unit_cost === null || Money::toSen($line->unit_cost) < 0) {
            throw StockException::invalidLine($document, $item, 'a receipt needs the unit cost.');
        }

        $value = Money::share(Money::toSen($line->unit_cost), $quantity, 100);

        $this->putIn($document, $line, $warehouse, StockMovementType::Receipt, $quantity, $value, $postedBy);
        $line->forceFill(['posted_value' => Money::toRupiah($value)])->save();
    }

    private function postIssue(StockDocument $document, StockDocumentLine $line, StockItem $item, Location $warehouse, int $quantity, ?User $postedBy): void
    {
        if ($quantity <= 0) {
            throw StockException::invalidLine($document, $item, 'the quantity must be more than zero.');
        }

        $value = $this->takeOut($document, $line, $warehouse, StockMovementType::Issue, $quantity, $postedBy);
        $line->forceFill(['posted_value' => Money::toRupiah($value)])->save();
    }

    private function postTransfer(StockDocument $document, StockDocumentLine $line, StockItem $item, Location $warehouse, Location $destination, int $quantity, ?User $postedBy): void
    {
        if ($quantity <= 0) {
            throw StockException::invalidLine($document, $item, 'the quantity must be more than zero.');
        }

        // The goods arrive carrying the value they left the source warehouse with.
        $value = $this->takeOut($document, $line, $warehouse, StockMovementType::TransferOut, $quantity, $postedBy);
        $this->putIn($document, $line, $destination, StockMovementType::TransferIn, $quantity, $value, $postedBy);
        $line->forceFill(['posted_value' => Money::toRupiah($value)])->save();
    }

    private function postAdjustment(StockDocument $document, StockDocumentLine $line, StockItem $item, Location $warehouse, int $change, ?User $postedBy): void
    {
        if ($change === 0) {
            throw StockException::invalidLine($document, $item, 'an adjustment must change the quantity.');
        }

        $value = $this->applyChange($document, $line, $item, $warehouse, StockMovementType::Adjustment, $change, $postedBy);
        $line->forceFill(['posted_value' => Money::toRupiah($value)])->save();
    }

    /**
     * Brings the warehouse's stock to the counted quantity. The difference is taken
     * against the stock on hand when the count is posted, not when it was entered.
     */
    private function postCount(StockDocument $document, StockDocumentLine $line, StockItem $item, Location $warehouse, int $counted, ?User $postedBy): void
    {
        if ($counted < 0) {
            throw StockException::invalidLine($document, $item, 'the counted quantity cannot be negative.');
        }

        $onHand = Quantity::toHundredths($this->lockedBalance($item, $warehouse)->quantity);
        $change = $counted - $onHand;

        $value = $change === 0
            ? 0
            : $this->applyChange($document, $line, $item, $warehouse, StockMovementType::Count, $change, $postedBy);

        $line->forceFill([
            'system_quantity' => Quantity::toDecimal($onHand),
            'posted_value' => Money::toRupiah($value),
        ])->save();
    }

    /**
     * A signed change with no purchase behind it. Returns the signed value moved.
     */
    private function applyChange(StockDocument $document, StockDocumentLine $line, StockItem $item, Location $warehouse, StockMovementType $type, int $change, ?User $postedBy): int
    {
        if ($change < 0) {
            return -$this->takeOut($document, $line, $warehouse, $type, -$change, $postedBy);
        }

        $value = $this->valueOfFoundStock($item, $this->lockedBalance($item, $warehouse), $change);
        $this->putIn($document, $line, $warehouse, $type, $change, $value, $postedBy);

        return $value;
    }

    /**
     * Stock found or written up has no purchase price, so it takes the warehouse's
     * average cost — or the item's last purchase price when the warehouse holds none.
     */
    private function valueOfFoundStock(StockItem $item, StockBalance $balance, int $quantity): int
    {
        $onHand = Quantity::toHundredths($balance->quantity);

        if ($onHand > 0) {
            return Money::share(Money::toSen($balance->total_value), $quantity, $onHand);
        }

        $lastPurchasePrice = StockDocumentLine::query()
            ->where('stock_item_id', $item->id)
            ->whereNotNull('unit_cost')
            ->whereHas('stockDocument', fn ($query) => $query
                ->where('type', StockDocumentType::Receipt->value)
                ->where('status', StockDocumentStatus::Posted->value))
            ->orderByDesc('id')
            ->value('unit_cost');

        return $lastPurchasePrice === null ? 0 : Money::share(Money::toSen($lastPurchasePrice), $quantity, 100);
    }

    /**
     * Removes stock at its average cost and returns the value removed.
     */
    private function takeOut(StockDocument $document, StockDocumentLine $line, Location $warehouse, StockMovementType $type, int $quantity, ?User $postedBy): int
    {
        $balance = $this->lockedBalance($line->stockItem, $warehouse);
        $onHand = Quantity::toHundredths($balance->quantity);

        if ($quantity > $onHand) {
            throw StockException::insufficient($line->stockItem, $warehouse, $onHand, $quantity);
        }

        $value = Money::share(Money::toSen($balance->total_value), $quantity, $onHand);

        $this->record($document, $line, $balance, $type, -$quantity, -$value, $postedBy);

        return $value;
    }

    private function putIn(StockDocument $document, StockDocumentLine $line, Location $warehouse, StockMovementType $type, int $quantity, int $value, ?User $postedBy): void
    {
        $balance = $this->lockedBalance($line->stockItem, $warehouse);

        $this->record($document, $line, $balance, $type, $quantity, $value, $postedBy);
    }

    private function record(StockDocument $document, StockDocumentLine $line, StockBalance $balance, StockMovementType $type, int $quantity, int $value, ?User $postedBy): void
    {
        $balanceQuantity = Quantity::toHundredths($balance->quantity) + $quantity;
        $balanceValue = Money::toSen($balance->total_value) + $value;

        $balance->forceFill([
            'quantity' => Quantity::toDecimal($balanceQuantity),
            'total_value' => Money::toRupiah($balanceValue),
        ])->save();

        StockMovement::create([
            'stock_item_id' => $balance->stock_item_id,
            'location_id' => $balance->location_id,
            'stock_document_id' => $document->id,
            'stock_document_line_id' => $line->id,
            'movement_type' => $type,
            'moved_on' => $document->document_date,
            'quantity' => Quantity::toDecimal($quantity),
            'value' => Money::toRupiah($value),
            'balance_quantity' => Quantity::toDecimal($balanceQuantity),
            'balance_value' => Money::toRupiah($balanceValue),
            'created_by' => $postedBy?->id,
        ]);
    }

    /**
     * The balance row for an item in a warehouse, created when missing and locked
     * until the posting commits.
     */
    private function lockedBalance(StockItem $item, Location $warehouse): StockBalance
    {
        StockBalance::query()->createOrFirst(
            ['stock_item_id' => $item->id, 'location_id' => $warehouse->id],
            ['quantity' => 0, 'total_value' => 0],
        );

        return StockBalance::query()
            ->where('stock_item_id', $item->id)
            ->where('location_id', $warehouse->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function warehouse(StockDocument $document, ?int $locationId): Location
    {
        $location = $locationId === null ? null : Location::query()->find($locationId);

        if ($location === null || $location->type !== LocationType::Warehouse || ! $location->is_active) {
            throw StockException::notAWarehouse($document, $location);
        }

        return $location;
    }

    /**
     * Stock on hand across every warehouse per item on the document, in hundredths.
     *
     * @param  Collection<int, StockDocumentLine>  $lines
     * @return array<int, int>
     */
    private function totalsOnHand(Collection $lines): array
    {
        $itemIds = $lines->pluck('stock_item_id')->unique()->values();

        $totals = $itemIds->mapWithKeys(fn (int $id): array => [$id => 0])->all();

        StockBalance::query()
            ->whereIn('stock_item_id', $itemIds)
            ->get(['stock_item_id', 'quantity'])
            ->each(function (StockBalance $balance) use (&$totals): void {
                $totals[$balance->stock_item_id] += Quantity::toHundredths($balance->quantity);
            });

        return $totals;
    }

    /**
     * Items this posting took from at or above their minimum to below it. Items that
     * were already short are not reported again.
     *
     * @param  Collection<int, StockDocumentLine>  $lines
     * @param  array<int, int>  $onHandBefore
     * @return Collection<int, StockItem>
     */
    private function itemsThatFellBelowMinimum(Collection $lines, array $onHandBefore): Collection
    {
        $onHandAfter = $this->totalsOnHand($lines);

        return $lines->map(fn (StockDocumentLine $line): StockItem => $line->stockItem)
            ->unique('id')
            ->filter(function (StockItem $item) use ($onHandBefore, $onHandAfter): bool {
                $minimum = Quantity::toHundredths($item->minimum_quantity);

                return $minimum > 0
                    && $onHandBefore[$item->id] >= $minimum
                    && $onHandAfter[$item->id] < $minimum;
            })
            ->values();
    }
}
