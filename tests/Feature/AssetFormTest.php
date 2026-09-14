<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Models\Asset;
use App\Models\User;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cost_can_be_corrected_while_no_depreciation_is_posted(): void
    {
        $asset = $this->depreciableAsset();

        Livewire::actingAs($this->admin())
            ->test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['acquisition_cost' => 2_400_000, 'useful_life_months' => 24])
            ->call('save')
            ->assertHasNoFormErrors();

        $asset->refresh();
        $this->assertSame('2400000.00', $asset->acquisition_cost);
        $this->assertSame(24, $asset->useful_life_months);
    }

    public function test_the_cost_and_depreciation_settings_freeze_once_a_month_is_posted(): void
    {
        $asset = $this->depreciableAsset();
        app(DepreciationRunner::class)->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'), User::factory()->create());

        Livewire::actingAs($this->admin())
            ->test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['acquisition_cost' => 2_400_000, 'useful_life_months' => 24, 'name' => 'Renamed laptop'])
            ->call('save');

        $asset->refresh();
        $this->assertSame('1200000.00', $asset->acquisition_cost);
        $this->assertSame(12, $asset->useful_life_months);
        $this->assertSame('Renamed laptop', $asset->name);
    }

    public function test_a_calculated_but_unposted_month_does_not_freeze_the_cost(): void
    {
        $asset = $this->depreciableAsset();
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-01-01'));

        Livewire::actingAs($this->admin())
            ->test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['acquisition_cost' => 2_400_000])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2400000.00', $asset->refresh()->acquisition_cost);
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
            'status' => AssetStatus::Available,
        ]);
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        return $user;
    }
}
