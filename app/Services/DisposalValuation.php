<?php

namespace App\Services;

use App\Enums\DepreciationBook;
use App\Enums\DepreciationPeriodStatus;
use App\Exceptions\AssetDisposalException;
use App\Models\Asset;
use App\Models\DepreciationEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The book value an asset leaves the books at, in both books.
 *
 * The month of disposal is not depreciated — the mirror of the acquisition month
 * being depreciated in full. So the book value is the cost less the depreciation
 * posted up to the month before, and that depreciation must be posted: an
 * unposted month would leave the gain or loss open to change.
 */
class DisposalValuation
{
    public function __construct(
        private readonly DepreciationRunner $runner,
    ) {}

    /**
     * @return array{cost: int, commercial: array{accumulated: int, book_value: int}, fiscal: array{accumulated: int, book_value: int}}
     */
    public function value(Asset $asset, CarbonImmutable $disposalDate): array
    {
        $cost = Money::toSen((string) $asset->acquisition_cost);
        $disposalMonth = $disposalDate->startOfMonth();

        $accumulated = collect(DepreciationBook::cases())
            ->mapWithKeys(fn (DepreciationBook $book): array => [$book->value => $this->accumulatedBefore($asset, $book, $disposalMonth)]);

        return [
            'cost' => $cost,
            'commercial' => [
                'accumulated' => $accumulated[DepreciationBook::Commercial->value],
                'book_value' => $cost - $accumulated[DepreciationBook::Commercial->value],
            ],
            'fiscal' => [
                'accumulated' => $accumulated[DepreciationBook::Fiscal->value],
                'book_value' => $cost - $accumulated[DepreciationBook::Fiscal->value],
            ],
        ];
    }

    private function accumulatedBefore(Asset $asset, DepreciationBook $book, CarbonImmutable $disposalMonth): int
    {
        $postedEntries = DepreciationEntry::query()
            ->where('asset_id', $asset->id)
            ->where('book', $book->value)
            ->whereHas('depreciationPeriod', fn (Builder $query) => $query->where('status', DepreciationPeriodStatus::Posted->value));

        if ((clone $postedEntries)->where('period', '>=', $disposalMonth->toDateString())->exists()) {
            throw AssetDisposalException::depreciatedInDisposalMonth($asset, $book, $disposalMonth);
        }

        if (! $asset->is_depreciable) {
            return 0;
        }

        $dueMonths = collect($this->runner->schedule($asset, $book))
            ->pluck('period')
            ->filter(fn (string $period): bool => $period < $disposalMonth->toDateString());

        if ($dueMonths->isEmpty()) {
            return 0;
        }

        $lastDue = CarbonImmutable::parse($dueMonths->max());
        $lastPosted = $this->runner->lastPostedMonth($book);

        if ($lastPosted === null || $lastPosted->lessThan($lastDue)) {
            throw AssetDisposalException::depreciationNotPosted($asset, $book, $lastDue);
        }

        $accumulated = $postedEntries
            ->where('period', '<', $disposalMonth->toDateString())
            ->orderByDesc('period')
            ->value('accumulated');

        return $accumulated === null ? 0 : Money::toSen($accumulated);
    }
}
