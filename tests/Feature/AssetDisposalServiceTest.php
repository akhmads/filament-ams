<?php

namespace Tests\Feature;

use App\Enums\AssetDisposalStatus;
use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\FiscalAssetGroup;
use App\Enums\MovementType;
use App\Exceptions\AssetDisposalException;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetDisposalLine;
use App\Models\AssetMovement;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AssetDisposalDecided;
use App\Services\AssetDisposalService;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssetDisposalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-20 10:00:00');
    }

    public function test_a_sale_writes_the_asset_off_with_its_gain_or_loss_in_both_books(): void
    {
        $asset = $this->depreciatingLaptop();
        $this->postDepreciationThrough('2026-08-01');
        $disposal = AssetDisposal::factory()->approved()->create(['disposal_date' => '2026-09-10']);
        $line = AssetDisposalLine::factory()->for($disposal)->for($asset)->sold('5000000')->create();

        $this->service()->complete($disposal, User::factory()->create());

        $line->refresh();
        // Commercial: 1,000,000 a month for January–August; fiscal (group 1): 250,000 a month.
        $this->assertSame('8000000.00', $line->commercial_accumulated);
        $this->assertSame('4000000.00', $line->commercial_book_value);
        $this->assertSame('1000000.00', $line->commercial_gain_loss);
        $this->assertSame('10000000.00', $line->fiscal_book_value);
        $this->assertSame('-5000000.00', $line->fiscal_gain_loss);

        $this->assertSame(AssetStatus::Disposed, $asset->refresh()->status);
        $this->assertSame(AssetDisposalStatus::Completed, $disposal->refresh()->status);

        $movement = AssetMovement::query()->whereKey($line->disposal_movement_id)->sole();
        $this->assertSame(MovementType::Disposal, $movement->movement_type);
        $this->assertTrue($movement->reference->is($disposal));
    }

    public function test_the_month_of_disposal_is_not_depreciated(): void
    {
        $asset = $this->depreciatingLaptop();
        $this->postDepreciationThrough('2026-08-01');
        $disposal = AssetDisposal::factory()->approved()->create(['disposal_date' => '2026-09-10']);
        AssetDisposalLine::factory()->for($disposal)->for($asset)->create();

        $this->service()->complete($disposal, User::factory()->create());

        $september = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2026-09-01'));
        $this->assertSame(0, $september->asset_count);
    }

    public function test_a_disposal_waits_until_the_month_before_is_posted(): void
    {
        $asset = $this->depreciatingLaptop();
        $this->postDepreciationThrough('2026-07-01');
        $disposal = AssetDisposal::factory()->approved()->create(['disposal_date' => '2026-09-10']);
        AssetDisposalLine::factory()->for($disposal)->for($asset)->create();

        try {
            $this->service()->complete($disposal, User::factory()->create());
            $this->fail('A disposal should not complete before August is posted.');
        } catch (AssetDisposalException $exception) {
            $this->assertStringContainsString('August 2026', $exception->getMessage());
        }

        $this->assertSame(AssetDisposalStatus::Approved, $disposal->refresh()->status);
        $this->assertSame(AssetStatus::Retired, $asset->refresh()->status);
    }

    public function test_an_asset_already_depreciated_in_the_disposal_month_cannot_be_written_off_that_month(): void
    {
        $asset = $this->depreciatingLaptop();
        $this->postDepreciationThrough('2026-09-01');
        $disposal = AssetDisposal::factory()->approved()->create(['disposal_date' => '2026-09-10']);
        AssetDisposalLine::factory()->for($disposal)->for($asset)->create();

        $this->expectException(AssetDisposalException::class);

        $this->service()->complete($disposal, User::factory()->create());
    }

    public function test_an_asset_acquired_in_the_disposal_month_leaves_at_cost(): void
    {
        $asset = $this->depreciatingLaptop(['acquisition_date' => '2026-09-02']);
        $disposal = AssetDisposal::factory()->approved()->create(['disposal_date' => '2026-09-15']);
        $line = AssetDisposalLine::factory()->for($disposal)->for($asset)->sold('11000000')->create();

        $this->service()->complete($disposal, User::factory()->create());

        $line->refresh();
        $this->assertSame('12000000.00', $line->commercial_book_value);
        $this->assertSame('-1000000.00', $line->commercial_gain_loss);
    }

    public function test_an_asset_that_is_not_depreciated_needs_no_posted_depreciation(): void
    {
        $asset = $this->depreciatingLaptop(['is_depreciable' => false]);
        $disposal = AssetDisposal::factory()->approved()->create(['disposal_date' => '2026-09-10']);
        $line = AssetDisposalLine::factory()->for($disposal)->for($asset)->create();

        $this->service()->complete($disposal, User::factory()->create());

        $this->assertSame('-12000000.00', $line->refresh()->commercial_gain_loss);
    }

    public function test_a_lost_asset_can_be_written_off(): void
    {
        $asset = $this->depreciatingLaptop(['is_depreciable' => false]);
        $asset->forceFill(['status' => AssetStatus::Lost])->save();
        $disposal = AssetDisposal::factory()->approved()->create();
        AssetDisposalLine::factory()->for($disposal)->for($asset)->create(['method' => 'lost']);

        $this->service()->complete($disposal, User::factory()->create());

        $this->assertSame(AssetStatus::Disposed, $asset->refresh()->status);
    }

    public function test_only_a_sale_or_trade_in_can_bring_in_proceeds(): void
    {
        $asset = $this->depreciatingLaptop(['is_depreciable' => false]);
        $disposal = AssetDisposal::factory()->approved()->create();
        AssetDisposalLine::factory()->for($disposal)->for($asset)->create(['method' => 'donation', 'proceeds' => '100000']);

        $this->expectException(AssetDisposalException::class);

        $this->service()->complete($disposal, User::factory()->create());
    }

    public function test_a_disposal_dated_in_the_future_cannot_be_completed_yet(): void
    {
        $disposal = AssetDisposal::factory()->approved()->create(['disposal_date' => '2026-09-21']);
        AssetDisposalLine::factory()->for($disposal)->for($this->depreciatingLaptop(['is_depreciable' => false]))->create();

        $this->expectException(AssetDisposalException::class);

        $this->service()->complete($disposal, User::factory()->create());
    }

    public function test_an_asset_still_held_by_an_employee_cannot_be_approved_for_disposal(): void
    {
        $asset = Asset::factory()->heldBy(Employee::factory()->create())->create();
        $asset->forceFill(['status' => AssetStatus::Available])->save();
        $disposal = AssetDisposal::factory()->create();
        AssetDisposalLine::factory()->for($disposal)->for($asset)->create();

        $this->expectException(AssetDisposalException::class);

        $this->service()->approve($disposal, User::factory()->create());
    }

    public function test_an_asset_cannot_be_on_two_open_disposals(): void
    {
        $asset = $this->depreciatingLaptop();
        AssetDisposalLine::factory()->for(AssetDisposal::factory()->approved()->create(['number' => 'DSP/2609/0001']))->for($asset)->create();
        $second = AssetDisposal::factory()->create();
        AssetDisposalLine::factory()->for($second)->for($asset)->create();

        try {
            $this->service()->approve($second, User::factory()->create());
            $this->fail('The asset is already on an open disposal.');
        } catch (AssetDisposalException $exception) {
            $this->assertStringContainsString('DSP/2609/0001', $exception->getMessage());
        }

        $this->assertSame(AssetDisposalStatus::Proposed, $second->refresh()->status);
    }

    public function test_whoever_proposed_the_disposal_hears_the_decision(): void
    {
        Notification::fake();
        $proposer = User::factory()->create();
        $disposal = AssetDisposal::factory()->create(['created_by' => $proposer->id]);
        AssetDisposalLine::factory()->for($disposal)->for($this->depreciatingLaptop())->create();

        $this->service()->reject($disposal, 'Still usable as a spare', User::factory()->create());

        Notification::assertSentTo($proposer, AssetDisposalDecided::class);
    }

    /**
     * A retired laptop in a warehouse: Rp 12,000,000, straight line over 12 months
     * commercially and in fiscal group 1 (48 months).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function depreciatingLaptop(array $attributes = []): Asset
    {
        return Asset::factory()
            ->inWarehouse(Location::factory()->warehouse()->create())
            ->status(AssetStatus::Retired)
            ->create([
                'acquisition_date' => '2026-01-15',
                'acquisition_cost' => 12_000_000,
                'depreciation_method' => DepreciationMethod::StraightLine,
                'useful_life_months' => 12,
                'residual_value' => 0,
                'fiscal_group' => FiscalAssetGroup::GroupOne,
                'fiscal_method' => DepreciationMethod::StraightLine,
                ...$attributes,
            ]);
    }

    private function postDepreciationThrough(string $month): void
    {
        $postedBy = User::factory()->create();
        $last = CarbonImmutable::parse($month);

        foreach (DepreciationBook::cases() as $book) {
            for ($period = CarbonImmutable::parse('2026-01-01'); $period->lessThanOrEqualTo($last); $period = $period->addMonth()) {
                app(DepreciationRunner::class)->post($book, $period, $postedBy);
            }
        }
    }

    private function service(): AssetDisposalService
    {
        return app(AssetDisposalService::class);
    }
}
