<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\DepreciationPeriodStatus;
use App\Enums\FiscalAssetGroup;
use App\Models\Asset;
use App\Models\DepreciationPeriod;
use App\Models\User;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CalculateDepreciationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_drafts_both_books_for_the_current_month_by_default(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-03-15 10:00'));
        $this->depreciableAsset();

        $this->artisan('depreciation:calculate')->assertSuccessful();

        $this->assertDatabaseCount('depreciation_periods', 2);
        foreach (DepreciationBook::cases() as $book) {
            $period = DepreciationPeriod::query()->where('book', $book)->whereDate('period', '2024-03-01')->sole();
            $this->assertSame(DepreciationPeriodStatus::Draft, $period->status);
            $this->assertSame(1, $period->asset_count);
        }
    }

    public function test_it_drafts_only_the_requested_book_and_month(): void
    {
        $this->depreciableAsset();

        $this->artisan('depreciation:calculate', ['--book' => ['fiscal'], '--month' => '2024-02'])->assertSuccessful();

        $period = DepreciationPeriod::query()->sole();
        $this->assertSame(DepreciationBook::Fiscal, $period->book);
        $this->assertSame('2024-02-01', $period->period->toDateString());
    }

    public function test_a_posted_month_is_skipped_with_a_warning_rather_than_failing(): void
    {
        $this->depreciableAsset();
        app(DepreciationRunner::class)->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'), User::factory()->create());

        $this->artisan('depreciation:calculate', ['--book' => ['commercial'], '--month' => '2024-03'])
            ->expectsOutputToContain('already posted')
            ->assertSuccessful();
    }

    public function test_an_unknown_book_is_rejected_without_calculating_anything(): void
    {
        $this->artisan('depreciation:calculate', ['--book' => ['tax'], '--month' => '2024-03'])
            ->expectsOutputToContain('Unknown book [tax]')
            ->assertExitCode(Command::INVALID);

        $this->assertDatabaseCount('depreciation_periods', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedMonths(): array
    {
        return [
            'month out of range' => ['2024-13'],
            'month name' => ['March 2024'],
            'full date' => ['2024-03-01'],
        ];
    }

    #[DataProvider('malformedMonths')]
    public function test_a_malformed_month_is_rejected_without_calculating_anything(string $month): void
    {
        $this->artisan('depreciation:calculate', ['--month' => $month])
            ->expectsOutputToContain('must look like 2026-09')
            ->assertExitCode(Command::INVALID);

        $this->assertDatabaseCount('depreciation_periods', 0);
    }

    private function depreciableAsset(): Asset
    {
        return Asset::factory()->create([
            'acquisition_date' => '2024-01-01',
            'acquisition_cost' => 1_200_000,
            'residual_value' => 0,
            'is_depreciable' => true,
            'depreciation_method' => DepreciationMethod::StraightLine,
            'useful_life_months' => 12,
            'fiscal_group' => FiscalAssetGroup::GroupOne,
            'status' => AssetStatus::Available,
        ]);
    }
}
