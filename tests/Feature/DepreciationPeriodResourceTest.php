<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\DepreciationPeriodStatus;
use App\Enums\FiscalAssetGroup;
use App\Filament\Resources\DepreciationPeriods\DepreciationPeriodResource;
use App\Filament\Resources\DepreciationPeriods\Pages\ListDepreciationPeriods;
use App\Filament\Resources\DepreciationPeriods\Pages\ViewDepreciationPeriod;
use App\Filament\Resources\DepreciationPeriods\RelationManagers\EntriesRelationManager;
use App\Models\Asset;
use App\Models\DepreciationPeriod;
use App\Models\User;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DepreciationPeriodResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_periods_list_renders(): void
    {
        $this->depreciableAsset();
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(DepreciationPeriodResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('March 2024');
    }

    public function test_a_period_lists_each_assets_entry(): void
    {
        $this->depreciableAsset(['code' => 'LAP-HO-2401-0001']);
        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(EntriesRelationManager::class, ['ownerRecord' => $period, 'pageClass' => ViewDepreciationPeriod::class])
            ->assertSee('LAP-HO-2401-0001');
    }

    public function test_calculate_month_drafts_the_chosen_book_and_month(): void
    {
        $this->depreciableAsset();

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ListDepreciationPeriods::class)
            ->callAction('calculateDepreciation', ['book' => DepreciationBook::Fiscal->value, 'month' => '2024-03-01']);

        $period = DepreciationPeriod::query()->sole();
        $this->assertSame(DepreciationBook::Fiscal, $period->book);
        $this->assertSame('2024-03-01', $period->period->toDateString());
        $this->assertSame(DepreciationPeriodStatus::Draft, $period->status);
    }

    public function test_posting_from_the_period_page_locks_it_and_records_who_posted(): void
    {
        $this->depreciableAsset();
        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));
        $manager = $this->userWithRole('asset_manager');

        Livewire::actingAs($manager)
            ->test(ViewDepreciationPeriod::class, ['record' => $period->getKey()])
            ->callAction('postDepreciation');

        $period->refresh();
        $this->assertSame(DepreciationPeriodStatus::Posted, $period->status);
        $this->assertSame($manager->id, $period->posted_by);
    }

    public function test_a_draft_can_be_posted_from_the_periods_table(): void
    {
        $this->depreciableAsset();
        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ListDepreciationPeriods::class)
            ->callAction(TestAction::make('postDepreciation')->table($period));

        $this->assertSame(DepreciationPeriodStatus::Posted, $period->refresh()->status);
    }

    public function test_a_posted_period_offers_neither_recalculate_nor_post(): void
    {
        $this->depreciableAsset();
        $period = app(DepreciationRunner::class)->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'), User::factory()->create());

        Livewire::actingAs($this->userWithRole('super_admin'))
            ->test(ViewDepreciationPeriod::class, ['record' => $period->getKey()])
            ->assertActionHidden('postDepreciation')
            ->assertActionHidden('recalculateDepreciation');
    }

    public function test_an_auditor_can_open_a_period_but_cannot_calculate_recalculate_or_post(): void
    {
        $this->depreciableAsset();
        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));
        $auditor = $this->userWithRole('auditor');

        Livewire::actingAs($auditor)
            ->test(ListDepreciationPeriods::class)
            ->assertActionHidden('calculateDepreciation');

        Livewire::actingAs($auditor)
            ->test(ViewDepreciationPeriod::class, ['record' => $period->getKey()])
            ->assertActionHidden('recalculateDepreciation')
            ->assertActionHidden('postDepreciation');
    }

    public function test_an_asset_manager_can_calculate_and_post(): void
    {
        $this->depreciableAsset();
        $period = app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));
        $manager = $this->userWithRole('asset_manager');

        Livewire::actingAs($manager)
            ->test(ListDepreciationPeriods::class)
            ->assertActionVisible('calculateDepreciation');

        Livewire::actingAs($manager)
            ->test(ViewDepreciationPeriod::class, ['record' => $period->getKey()])
            ->assertActionVisible('postDepreciation');
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function depreciableAsset(array $attributes = []): Asset
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
            ...$attributes,
        ]);
    }
}
