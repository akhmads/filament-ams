<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\FiscalAssetGroup;
use App\Filament\Pages\BookValueReport;
use App\Filament\Pages\DepreciationJournalReport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\DepreciationEntry;
use App\Models\User;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class DepreciationReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_reports_render_for_a_role_that_can_view_depreciation(): void
    {
        $this->depreciableAsset();
        $this->postThrough('2024-02');
        $auditor = $this->userWithRole('auditor');

        $this->actingAs($auditor)
            ->get(BookValueReport::getUrl())
            ->assertSuccessful()
            ->assertSee('Book Value');

        $this->actingAs($auditor)
            ->get(DepreciationJournalReport::getUrl())
            ->assertSuccessful()
            ->assertSee('Depreciation Journal');
    }

    public function test_a_user_without_depreciation_access_cannot_open_the_reports(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(BookValueReport::getUrl())->assertForbidden();
        $this->actingAs($user)->get(DepreciationJournalReport::getUrl())->assertForbidden();
    }

    public function test_book_value_counts_posted_depreciation_only(): void
    {
        $this->depreciableAsset(['code' => 'LAP-0001']);
        $this->postThrough('2024-02');
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $rows = $this->bookValueRows(DepreciationBook::Commercial, '2024-03-01');

        $this->assertEqualsWithDelta(1_200_000, (float) $rows['LAP-0001']->acquisition_cost, 0.001);
        $this->assertEqualsWithDelta(200_000, (float) $rows['LAP-0001']->accumulated_depreciation, 0.001);
        $this->assertEqualsWithDelta(1_000_000, (float) $rows['LAP-0001']->book_value, 0.001);
    }

    public function test_book_value_can_be_read_as_of_an_earlier_posted_month(): void
    {
        $this->depreciableAsset(['code' => 'LAP-0001']);
        $this->postThrough('2024-03');

        $rows = $this->bookValueRows(DepreciationBook::Commercial, '2024-01-01');

        $this->assertEqualsWithDelta(100_000, (float) $rows['LAP-0001']->accumulated_depreciation, 0.001);
        $this->assertEqualsWithDelta(1_100_000, (float) $rows['LAP-0001']->book_value, 0.001);
    }

    public function test_book_value_keeps_the_books_apart(): void
    {
        $this->depreciableAsset(['code' => 'LAP-0001']);
        $this->postThrough('2024-02');

        $rows = $this->bookValueRows(DepreciationBook::Fiscal, '2024-02-01');

        $this->assertEqualsWithDelta(0, (float) $rows['LAP-0001']->accumulated_depreciation, 0.001);
        $this->assertEqualsWithDelta(1_200_000, (float) $rows['LAP-0001']->book_value, 0.001);
    }

    public function test_book_value_leaves_out_assets_acquired_after_the_month_and_non_depreciable_assets(): void
    {
        $this->depreciableAsset(['code' => 'LAP-0001']);
        $this->depreciableAsset(['code' => 'LAP-LATER', 'acquisition_date' => '2024-04-01']);
        $this->depreciableAsset(['code' => 'LAP-NODEP', 'is_depreciable' => false]);

        $rows = $this->bookValueRows(DepreciationBook::Commercial, '2024-03-01');

        $this->assertSame(['LAP-0001'], $rows->keys()->all());
    }

    public function test_the_journal_sums_a_period_per_category_with_its_accounts(): void
    {
        $computers = AssetCategory::factory()->create([
            'code' => 'CMP',
            'name' => 'Computers',
            'expense_account_code' => '6101',
            'expense_account_name' => 'Depreciation Expense - Computers',
            'accumulated_account_code' => '1291',
            'accumulated_account_name' => 'Accumulated Depreciation - Computers',
        ]);
        $vehicles = AssetCategory::factory()->create(['code' => 'VHC', 'name' => 'Vehicles']);
        $this->depreciableAsset(['asset_category_id' => $computers->id]);
        $this->depreciableAsset(['asset_category_id' => $computers->id, 'acquisition_cost' => 2_400_000]);
        $this->depreciableAsset(['asset_category_id' => $vehicles->id, 'acquisition_cost' => 600_000]);
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $rows = $this->journalRows(DepreciationBook::Commercial, '2024-03-01')->keyBy('category_code');

        $this->assertSame(['CMP', 'VHC'], $rows->keys()->all());
        $this->assertSame(2, (int) $rows['CMP']->asset_count);
        $this->assertEqualsWithDelta(300_000, (float) $rows['CMP']->amount, 0.001);
        $this->assertSame('6101', $rows['CMP']->expense_account_code);
        $this->assertSame('1291', $rows['CMP']->accumulated_account_code);
        $this->assertEqualsWithDelta(50_000, (float) $rows['VHC']->amount, 0.001);
        $this->assertNull($rows['VHC']->expense_account_code);
    }

    public function test_the_journal_keeps_an_asset_that_was_deleted_after_depreciating(): void
    {
        $asset = $this->depreciableAsset();
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));
        $asset->delete();

        $rows = $this->journalRows(DepreciationBook::Commercial, '2024-03-01');

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100_000, (float) $rows->first()->amount, 0.001);
    }

    public function test_the_journal_is_empty_for_a_month_that_was_not_calculated(): void
    {
        $this->depreciableAsset();
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $this->assertCount(0, $this->journalRows(DepreciationBook::Fiscal, '2024-03-01'));
        $this->assertCount(0, $this->journalRows(DepreciationBook::Commercial, '2024-04-01'));
    }

    public function test_the_journal_says_whether_the_period_is_posted(): void
    {
        $this->depreciableAsset();
        $manager = $this->userWithRole('asset_manager');
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        Livewire::actingAs($manager)
            ->test(DepreciationJournalReport::class)
            ->filterTable('report', ['book' => DepreciationBook::Commercial->value, 'month' => '2024-03-01'])
            ->assertSee('draft. Post the period before booking this journal.')
            ->assertActionVisible('openPeriod');

        app(DepreciationRunner::class)->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'), $manager);

        Livewire::actingAs($manager)
            ->test(DepreciationJournalReport::class)
            ->filterTable('report', ['book' => DepreciationBook::Commercial->value, 'month' => '2024-03-01'])
            ->assertSee('March 2024: posted')
            ->assertSee($manager->name)
            ->assertDontSee('draft. Post the period before booking this journal.');
    }

    /**
     * Posts the commercial book month by month from January 2024 through the given month.
     */
    private function postThrough(string $lastMonth): void
    {
        $user = User::factory()->create();
        $last = CarbonImmutable::parse("{$lastMonth}-01");

        for ($month = CarbonImmutable::parse('2024-01-01'); $month->lte($last); $month = $month->addMonth()) {
            app(DepreciationRunner::class)->post(DepreciationBook::Commercial, $month, $user);
        }
    }

    /**
     * @return Collection<string, Asset>
     */
    private function bookValueRows(DepreciationBook $book, string $month): Collection
    {
        return collect(
            Livewire::actingAs($this->userWithRole('super_admin'))
                ->test(BookValueReport::class)
                ->filterTable('report', ['book' => $book->value, 'month' => $month])
                ->instance()
                ->getTableRecords()
                ->items()
        )->keyBy('code');
    }

    /**
     * @return Collection<int, DepreciationEntry>
     */
    private function journalRows(DepreciationBook $book, string $month): Collection
    {
        return collect(
            Livewire::actingAs($this->userWithRole('super_admin'))
                ->test(DepreciationJournalReport::class)
                ->filterTable('report', ['book' => $book->value, 'month' => $month])
                ->instance()
                ->getTableRecords()
        );
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
