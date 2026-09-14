<?php

namespace App\Console\Commands;

use App\Enums\DepreciationBook;
use App\Exceptions\DepreciationException;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Refreshes draft depreciation for a month. Posting stays a manual, reviewed step
 * in the panel, so this command never posts.
 */
class CalculateDepreciation extends Command
{
    protected $signature = 'depreciation:calculate
        {--book=* : commercial or fiscal; both when omitted}
        {--month= : The month as YYYY-MM; the current month when omitted}';

    protected $description = 'Calculate or refresh the draft depreciation for a month';

    public function handle(DepreciationRunner $runner): int
    {
        $books = $this->books();
        $month = $this->month();

        if ($books === null || $month === null) {
            return self::INVALID;
        }

        foreach ($books as $book) {
            try {
                $period = $runner->calculate($book, $month);
            } catch (DepreciationException $exception) {
                // A posted month is expected on reruns, not a failure.
                $this->components->warn($exception->getMessage());

                continue;
            }

            $this->components->info(sprintf(
                '%s %s: %d asset(s), total Rp %s',
                $book->getLabel(),
                $month->format('F Y'),
                $period->asset_count,
                $period->total_amount,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<DepreciationBook>|null
     */
    private function books(): ?array
    {
        $requested = $this->option('book');

        if ($requested === []) {
            return DepreciationBook::cases();
        }

        $books = [];

        foreach ($requested as $value) {
            $book = DepreciationBook::tryFrom($value);

            if ($book === null) {
                $this->components->error("Unknown book [{$value}]. Use commercial or fiscal.");

                return null;
            }

            $books[] = $book;
        }

        return $books;
    }

    private function month(): ?CarbonImmutable
    {
        $requested = $this->option('month');

        if ($requested === null) {
            return CarbonImmutable::now()->startOfMonth();
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested)) {
            $this->components->error("Month [{$requested}] must look like 2026-09.");

            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m', $requested);
    }
}
