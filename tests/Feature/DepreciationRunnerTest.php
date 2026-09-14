<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\DepreciationPeriodStatus;
use App\Enums\FiscalAssetGroup;
use App\Exceptions\DepreciationException;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\DepreciationEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepreciationRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculating_a_month_records_each_depreciable_assets_charge(): void
    {
        $asset = $this->asset(['acquisition_cost' => 12_000_000, 'useful_life_months' => 12, 'acquisition_date' => '2024-01-15']);

        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $entry = DepreciationEntry::query()->sole();
        $this->assertSame(DepreciationPeriodStatus::Draft, $period->status);
        $this->assertSame(1, $period->asset_count);
        $this->assertSame('1000000.00', $period->total_amount);
        $this->assertSame($asset->id, $entry->asset_id);
        $this->assertSame('10000000.00', $entry->opening_book_value);
        $this->assertSame('1000000.00', $entry->amount);
        $this->assertSame('3000000.00', $entry->accumulated);
        $this->assertSame('9000000.00', $entry->closing_book_value);
    }

    public function test_assets_outside_their_schedule_for_the_month_get_no_entry(): void
    {
        $this->asset(['acquisition_date' => '2024-06-01']);
        $this->asset(['acquisition_date' => '2023-01-01', 'useful_life_months' => 2]);

        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $this->assertSame(0, $period->asset_count);
        $this->assertDatabaseCount('depreciation_entries', 0);
    }

    public function test_disposed_lost_and_non_depreciable_assets_are_left_out(): void
    {
        $this->asset(['status' => AssetStatus::Disposed]);
        $this->asset(['status' => AssetStatus::Lost]);
        $this->asset(['is_depreciable' => false]);

        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $this->assertSame(0, $period->asset_count);
        $this->assertDatabaseCount('depreciation_entries', 0);
    }

    public function test_recalculating_a_draft_replaces_its_entries(): void
    {
        $asset = $this->asset(['acquisition_cost' => 12_000_000, 'useful_life_months' => 12]);
        $runner = app(DepreciationRunner::class);
        $runner->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));
        $asset->update(['acquisition_cost' => 24_000_000]);

        $runner->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $this->assertSame('2000000.00', DepreciationEntry::query()->sole()->amount);
    }

    public function test_the_fiscal_book_uses_the_tax_group_useful_life(): void
    {
        $this->asset([
            'acquisition_cost' => 9_600_000,
            'useful_life_months' => 12,
            'fiscal_group' => FiscalAssetGroup::GroupTwo,
        ]);
        $runner = app(DepreciationRunner::class);

        $commercial = $runner->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'));
        $fiscal = $runner->calculate(DepreciationBook::Fiscal, CarbonImmutable::parse('2024-01-01'));

        $this->assertSame('800000.00', $commercial->total_amount);
        $this->assertSame('100000.00', $fiscal->total_amount);
    }

    public function test_the_fiscal_book_starts_in_the_acquisition_month_whatever_the_commercial_start(): void
    {
        $this->asset([
            'acquisition_cost' => 9_600_000,
            'acquisition_date' => '2024-01-10',
            'depreciation_start_date' => '2024-03-01',
            'fiscal_group' => FiscalAssetGroup::GroupTwo,
        ]);
        $runner = app(DepreciationRunner::class);

        $commercial = $runner->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'));
        $fiscal = $runner->calculate(DepreciationBook::Fiscal, CarbonImmutable::parse('2024-01-01'));

        $this->assertSame(0, $commercial->asset_count);
        $this->assertSame(1, $fiscal->asset_count);
    }

    public function test_the_fiscal_group_falls_back_to_the_category(): void
    {
        $category = AssetCategory::factory()->create(['fiscal_group' => FiscalAssetGroup::GroupOne]);
        $this->asset(['acquisition_cost' => 4_800_000, 'asset_category_id' => $category->id, 'fiscal_group' => null]);

        $fiscal = app(DepreciationRunner::class)->calculate(DepreciationBook::Fiscal, CarbonImmutable::parse('2024-01-01'));

        $this->assertSame('100000.00', $fiscal->total_amount);
    }

    public function test_an_asset_without_a_fiscal_group_is_left_out_of_the_fiscal_book(): void
    {
        $this->asset(['fiscal_group' => null]);

        $fiscal = app(DepreciationRunner::class)->calculate(DepreciationBook::Fiscal, CarbonImmutable::parse('2024-01-01'));

        $this->assertSame(0, $fiscal->asset_count);
    }

    public function test_a_building_is_depreciated_straight_line_for_tax_even_if_declining_balance_is_chosen(): void
    {
        $this->asset([
            'acquisition_cost' => 24_000_000,
            'fiscal_group' => FiscalAssetGroup::PermanentBuilding,
            'fiscal_method' => DepreciationMethod::DoubleDeclining,
        ]);

        $fiscal = app(DepreciationRunner::class)->calculate(DepreciationBook::Fiscal, CarbonImmutable::parse('2024-01-01'));

        $this->assertSame('100000.00', $fiscal->total_amount);
    }

    public function test_the_declining_balance_year_follows_the_fiscal_year_setting(): void
    {
        Setting::set('fiscal_year_start_month', 4);
        $this->asset([
            'acquisition_cost' => 12_000_000,
            'useful_life_months' => 48,
            'depreciation_method' => DepreciationMethod::DoubleDeclining,
        ]);

        $april = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-04-01'));

        $this->assertSame('437500.00', $april->total_amount);
    }

    public function test_posting_locks_the_period_against_recalculation(): void
    {
        $this->asset();
        $user = User::factory()->create();
        $runner = app(DepreciationRunner::class);

        $period = $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'), $user);

        $this->assertSame(DepreciationPeriodStatus::Posted, $period->status);
        $this->assertSame($user->id, $period->posted_by);
        $this->assertNotNull($period->posted_at);
        $this->expectException(DepreciationException::class);
        $runner->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'));
    }

    public function test_posting_must_follow_the_last_posted_month(): void
    {
        $this->asset();
        $user = User::factory()->create();
        $runner = app(DepreciationRunner::class);
        $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'), $user);

        $this->expectException(DepreciationException::class);
        $this->expectExceptionMessage('post February 2024 before March 2024');

        $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'), $user);
    }

    public function test_the_next_month_can_be_posted_after_the_last_posted_one(): void
    {
        $this->asset();
        $user = User::factory()->create();
        $runner = app(DepreciationRunner::class);
        $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'), $user);

        $february = $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-02-01'), $user);

        $this->assertSame(DepreciationPeriodStatus::Posted, $february->status);
    }

    public function test_a_month_before_the_last_posted_one_cannot_be_recalculated(): void
    {
        $this->asset(['acquisition_date' => '2023-12-01']);
        $runner = app(DepreciationRunner::class);
        $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-02-01'), User::factory()->create());

        $this->expectException(DepreciationException::class);
        $this->expectExceptionMessage('posted up to February 2024');

        $runner->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'));
    }

    public function test_posting_recalculates_so_an_asset_added_after_the_draft_is_included(): void
    {
        $this->asset();
        $runner = app(DepreciationRunner::class);
        $runner->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'));
        $this->asset();

        $posted = $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'), User::factory()->create());

        $this->assertSame(2, $posted->asset_count);
    }

    public function test_each_book_keeps_its_own_posting_sequence(): void
    {
        $this->asset(['fiscal_group' => FiscalAssetGroup::GroupOne]);
        $user = User::factory()->create();
        $runner = app(DepreciationRunner::class);
        $runner->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'), $user);

        $fiscal = $runner->post(DepreciationBook::Fiscal, CarbonImmutable::parse('2024-03-01'), $user);

        $this->assertSame(DepreciationPeriodStatus::Posted, $fiscal->status);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function asset(array $attributes = []): Asset
    {
        return Asset::factory()->create([
            'acquisition_date' => '2024-01-01',
            'acquisition_cost' => 1_200_000,
            'residual_value' => 0,
            'is_depreciable' => true,
            'depreciation_method' => DepreciationMethod::StraightLine,
            'useful_life_months' => 12,
            'status' => AssetStatus::Available,
            ...$attributes,
        ]);
    }
}
