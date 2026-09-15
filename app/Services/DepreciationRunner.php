<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\DepreciationPeriodStatus;
use App\Enums\FiscalAssetGroup;
use App\Exceptions\DepreciationException;
use App\Models\Asset;
use App\Models\DepreciationEntry;
use App\Models\DepreciationPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns the calculator's schedules into stored monthly entries, and posts them.
 *
 * A period stays a draft until posted and can be recalculated as often as
 * needed. Posting recalculates first, so a stale draft is never locked in, and
 * must follow the last posted month of the same book without gaps.
 *
 * Assets that are disposed or lost are left out entirely: the disposal date and
 * the gain or loss belong to the disposal module (Pasal 11 ayat 8).
 */
class DepreciationRunner
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly DepreciationCalculator $calculator,
    ) {}

    public function calculate(DepreciationBook $book, CarbonImmutable $month): DepreciationPeriod
    {
        $month = $month->startOfMonth();

        return DB::transaction(function () use ($book, $month): DepreciationPeriod {
            $this->ensureNotLocked($book, $month);

            // Looked up by date rather than by the raw string: the date cast stores a
            // time part on drivers without a DATE type, so an equality match can miss
            // an existing period and trip the unique index.
            $period = DepreciationPeriod::query()
                ->where('book', $book)
                ->whereDate('period', $month->toDateString())
                ->first()
                ?? new DepreciationPeriod(['book' => $book, 'period' => $month->toDateString()]);

            if ($period->exists && $period->isPosted()) {
                throw DepreciationException::alreadyPosted($book, $month);
            }

            $period->save();
            $period->entries()->delete();

            $fiscalYearStartMonth = (int) Setting::get('fiscal_year_start_month', 1);
            $assetCount = 0;
            $totalSen = 0;

            $this->depreciableAssets($month)->chunkById(self::CHUNK_SIZE, function (Collection $assets) use ($period, $book, $month, $fiscalYearStartMonth, &$assetCount, &$totalSen): void {
                $now = now();
                $rows = [];

                foreach ($assets as $asset) {
                    $row = $this->scheduleRow($asset, $book, $month, $fiscalYearStartMonth);

                    if ($row === null) {
                        continue;
                    }

                    $rows[] = [
                        'depreciation_period_id' => $period->id,
                        'asset_id' => $asset->id,
                        'book' => $book->value,
                        'period' => $month->toDateString(),
                        'opening_book_value' => Money::toRupiah($row['opening']),
                        'amount' => Money::toRupiah($row['amount']),
                        'accumulated' => Money::toRupiah($row['accumulated']),
                        'closing_book_value' => Money::toRupiah($row['closing']),
                        'is_final' => $row['is_final'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $assetCount++;
                    $totalSen += $row['amount'];
                }

                if ($rows !== []) {
                    DepreciationEntry::query()->insert($rows);
                }
            });

            $period->forceFill([
                'asset_count' => $assetCount,
                'total_amount' => Money::toRupiah($totalSen),
                'calculated_at' => now(),
            ])->save();

            return $period->refresh();
        });
    }

    public function post(DepreciationBook $book, CarbonImmutable $month, User $postedBy): DepreciationPeriod
    {
        $month = $month->startOfMonth();

        return DB::transaction(function () use ($book, $month, $postedBy): DepreciationPeriod {
            $lastPosted = $this->lastPostedMonth($book, lock: true);

            if ($lastPosted !== null && ! $month->equalTo($lastPosted->addMonth())) {
                if ($month->lessThanOrEqualTo($lastPosted)) {
                    throw DepreciationException::alreadyPosted($book, $month);
                }

                throw DepreciationException::outOfSequence($book, $lastPosted->addMonth(), $month);
            }

            $period = $this->calculate($book, $month);

            $period->forceFill([
                'status' => DepreciationPeriodStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $postedBy->id,
            ])->save();

            return $period;
        });
    }

    private function ensureNotLocked(DepreciationBook $book, CarbonImmutable $month): void
    {
        $lastPosted = $this->lastPostedMonth($book);

        if ($lastPosted === null) {
            return;
        }

        if ($month->equalTo($lastPosted)) {
            throw DepreciationException::alreadyPosted($book, $month);
        }

        if ($month->lessThan($lastPosted)) {
            throw DepreciationException::lockedByLaterPosting($book, $month, $lastPosted);
        }
    }

    public function lastPostedMonth(DepreciationBook $book, bool $lock = false): ?CarbonImmutable
    {
        $query = DepreciationPeriod::query()
            ->where('book', $book)
            ->where('status', DepreciationPeriodStatus::Posted)
            ->orderByDesc('period');

        if ($lock) {
            $query->lockForUpdate();
        }

        $latest = $query->value('period');

        return $latest === null ? null : CarbonImmutable::parse($latest)->startOfMonth();
    }

    /**
     * @return Builder<Asset>
     */
    private function depreciableAssets(CarbonImmutable $month): Builder
    {
        return Asset::query()
            ->with('category')
            ->where('is_depreciable', true)
            ->whereNotIn('status', [AssetStatus::Disposed->value, AssetStatus::Lost->value])
            ->whereDate('acquisition_date', '<=', $month->endOfMonth()->toDateString());
    }

    /**
     * The asset's schedule row for the month in the given book, or null when the
     * month falls outside its schedule or the asset cannot be depreciated in it.
     *
     * @return array{period: string, opening: int, amount: int, accumulated: int, closing: int, is_final: bool}|null
     */
    private function scheduleRow(Asset $asset, DepreciationBook $book, CarbonImmutable $month, int $fiscalYearStartMonth): ?array
    {
        foreach ($this->schedule($asset, $book, $fiscalYearStartMonth) as $row) {
            if ($row['period'] === $month->toDateString()) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Every month the asset is depreciated in the book, with the accumulated amount
     * after each. Empty when the asset falls outside that book.
     *
     * @return list<array{period: string, opening: int, amount: int, accumulated: int, closing: int, is_final: bool}>
     */
    public function schedule(Asset $asset, DepreciationBook $book, ?int $fiscalYearStartMonth = null): array
    {
        $fiscalYearStartMonth ??= (int) Setting::get('fiscal_year_start_month', 1);
        $cost = Money::toSen((string) $asset->acquisition_cost);

        return match ($book) {
            DepreciationBook::Commercial => $this->calculator->schedule(
                costSen: $cost,
                residualSen: Money::toSen((string) $asset->residual_value),
                method: $asset->depreciation_method,
                usefulLifeMonths: (int) $asset->useful_life_months,
                start: CarbonImmutable::parse($asset->depreciation_start_date ?? $asset->acquisition_date),
                fiscalYearStartMonth: $fiscalYearStartMonth,
            ),
            DepreciationBook::Fiscal => $this->fiscalSchedule($asset, $cost, $fiscalYearStartMonth),
        };
    }

    /**
     * Tax depreciation follows statute rather than company policy: the group sets
     * the useful life, there is no residual value, it starts in the month of
     * acquisition (Pasal 11 ayat 3), and buildings are straight line only.
     *
     * @return list<array{period: string, opening: int, amount: int, accumulated: int, closing: int, is_final: bool}>
     */
    private function fiscalSchedule(Asset $asset, int $cost, int $fiscalYearStartMonth): array
    {
        $group = $asset->fiscal_group ?? $asset->category?->fiscal_group;

        if (! $group instanceof FiscalAssetGroup) {
            return [];
        }

        $method = $asset->fiscal_method === DepreciationMethod::DoubleDeclining && $group->allowsDecliningBalance()
            ? DepreciationMethod::DoubleDeclining
            : DepreciationMethod::StraightLine;

        return $this->calculator->schedule(
            costSen: $cost,
            residualSen: 0,
            method: $method,
            usefulLifeMonths: $group->usefulLifeMonths(),
            start: CarbonImmutable::parse($asset->acquisition_date),
            fiscalYearStartMonth: $fiscalYearStartMonth,
        );
    }
}
