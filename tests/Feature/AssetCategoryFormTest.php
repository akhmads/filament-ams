<?php

namespace Tests\Feature;

use App\Enums\DepreciationMethod;
use App\Enums\FiscalAssetGroup;
use App\Filament\Resources\AssetCategories\Pages\CreateAssetCategory;
use App\Models\AssetCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetCategoryFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_stores_its_tax_group_method_and_journal_accounts(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAssetCategory::class)
            ->fillForm([
                ...$this->validCategory(),
                'fiscal_group' => FiscalAssetGroup::GroupTwo->value,
                'fiscal_method' => DepreciationMethod::DoubleDeclining->value,
                'expense_account_code' => '6-1100',
                'expense_account_name' => 'Depreciation Expense — Vehicles',
                'accumulated_account_code' => '1-2190',
                'accumulated_account_name' => 'Accumulated Depreciation — Vehicles',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = AssetCategory::query()->where('code', 'KND')->sole();
        $this->assertSame(FiscalAssetGroup::GroupTwo, $category->fiscal_group);
        $this->assertSame(DepreciationMethod::DoubleDeclining, $category->fiscal_method);
        $this->assertSame('6-1100', $category->expense_account_code);
        $this->assertSame('Accumulated Depreciation — Vehicles', $category->accumulated_account_name);
    }

    public function test_a_building_category_cannot_use_declining_balance_for_tax(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAssetCategory::class)
            ->fillForm([
                ...$this->validCategory(),
                'fiscal_group' => FiscalAssetGroup::PermanentBuilding->value,
                'fiscal_method' => DepreciationMethod::DoubleDeclining->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['fiscal_method']);

        $this->assertDatabaseMissing('asset_categories', ['code' => 'KND']);
    }

    public function test_sum_of_years_digits_is_not_offered_for_tax(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAssetCategory::class)
            ->fillForm([
                ...$this->validCategory(),
                'fiscal_group' => FiscalAssetGroup::GroupOne->value,
                'fiscal_method' => DepreciationMethod::SumOfYearsDigits->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['fiscal_method']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validCategory(): array
    {
        return [
            'code' => 'KND',
            'prefix' => 'KND',
            'name' => 'Vehicles',
            'is_depreciable' => true,
            'depreciation_method' => DepreciationMethod::StraightLine->value,
            'useful_life_months' => 96,
            'residual_percent' => 0,
        ];
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        return $user;
    }
}
