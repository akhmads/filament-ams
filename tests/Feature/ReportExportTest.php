<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\DepreciationBook;
use App\Enums\DepreciationMethod;
use App\Enums\ExportFormat;
use App\Enums\FiscalAssetGroup;
use App\Filament\Pages\BookValueReport;
use App\Filament\Pages\DepreciationJournalReport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\User;
use App\Services\DepreciationRunner;
use App\Services\SpreadsheetExporter;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_keeps_dates_and_amounts_as_typed_cells(): void
    {
        $path = app(SpreadsheetExporter::class)->write(
            ExportFormat::Xlsx,
            ['Name', 'Date', 'Amount'],
            [['Laptop', CarbonImmutable::parse('2024-03-31'), 1500.5]],
        );

        $rows = $this->readXlsx($path);
        File::delete($path);

        $this->assertSame(['Name', 'Date', 'Amount'], $rows[0]);
        $this->assertSame('Laptop', $rows[1][0]);
        $this->assertInstanceOf(DateTimeInterface::class, $rows[1][1]);
        $this->assertSame('2024-03-31', $rows[1][1]->format('Y-m-d'));
        $this->assertEqualsWithDelta(1500.5, $rows[1][2], 0.001);
    }

    public function test_csv_uses_plain_numbers_and_iso_dates(): void
    {
        $path = app(SpreadsheetExporter::class)->write(
            ExportFormat::Csv,
            ['Name', 'Date', 'Amount'],
            [['Laptop, 14 inch', CarbonImmutable::parse('2024-03-31'), 1500.5]],
        );

        $content = File::get($path);
        File::delete($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'A BOM lets Excel read the file as UTF-8.');
        $this->assertSame(
            [['Name', 'Date', 'Amount'], ['Laptop, 14 inch', '2024-03-31', '1500.5']],
            $this->parseCsv($content),
        );
    }

    public function test_the_book_value_export_follows_the_filters_on_screen(): void
    {
        $laptops = AssetCategory::factory()->create(['name' => 'Laptops']);
        $branch = Branch::factory()->create(['name' => 'Head Office']);
        $this->depreciableAsset([
            'code' => 'LAP-0001',
            'name' => 'Laptop A',
            'asset_category_id' => $laptops->id,
            'branch_id' => $branch->id,
        ]);
        $this->depreciableAsset(['code' => 'CHR-0001']);
        $this->postThrough('2024-02');

        $component = Livewire::actingAs($this->userWithRole('auditor'))
            ->test(BookValueReport::class)
            ->filterTable('report', ['book' => DepreciationBook::Commercial->value, 'month' => '2024-03-01'])
            ->filterTable('asset_category_id', $laptops->id)
            ->callAction('exportCsv')
            ->assertFileDownloaded('book-value-commercial-2024-03.csv');

        $this->assertSame([
            ['Asset Code', 'Asset Name', 'Category', 'Branch', 'Acquisition Date', 'Cost', 'Accumulated Depreciation', 'Book Value', 'Status'],
            ['LAP-0001', 'Laptop A', 'Laptops', 'Head Office', '2024-01-01', '1200000', '200000', '1000000', 'Available'],
        ], $this->parseCsv($this->downloadedContent($component)));
    }

    public function test_the_book_value_report_exports_to_xlsx(): void
    {
        $this->depreciableAsset(['code' => 'LAP-0001']);
        $this->postThrough('2024-02');

        $component = Livewire::actingAs($this->userWithRole('auditor'))
            ->test(BookValueReport::class)
            ->filterTable('report', ['book' => DepreciationBook::Commercial->value, 'month' => '2024-03-01'])
            ->callAction('exportXlsx')
            ->assertFileDownloaded('book-value-commercial-2024-03.xlsx');

        $path = storage_path('framework/testing/book-value.xlsx');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->downloadedContent($component));
        $rows = $this->readXlsx($path);
        File::delete($path);

        $this->assertCount(2, $rows);
        $this->assertSame('LAP-0001', $rows[1][0]);
        $this->assertEqualsWithDelta(1_000_000, $rows[1][7], 0.001);
    }

    public function test_the_journal_exports_a_debit_and_a_credit_line_per_category(): void
    {
        $computers = AssetCategory::factory()->create([
            'code' => 'CMP',
            'name' => 'Computers',
            'expense_account_code' => '6101',
            'expense_account_name' => 'Depreciation Expense - Computers',
            'accumulated_account_code' => '1291',
            'accumulated_account_name' => 'Accumulated Depreciation - Computers',
        ]);
        $this->depreciableAsset(['asset_category_id' => $computers->id]);
        $this->depreciableAsset(['asset_category_id' => $computers->id, 'acquisition_cost' => 2_400_000]);
        app(DepreciationRunner::class)->calculate(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'));

        $component = Livewire::actingAs($this->userWithRole('auditor'))
            ->test(DepreciationJournalReport::class)
            ->filterTable('report', ['book' => DepreciationBook::Commercial->value, 'month' => '2024-03-01'])
            ->callAction('exportCsv')
            ->assertFileDownloaded('depreciation-journal-commercial-2024-03-draft.csv');

        $description = 'Commercial depreciation March 2024, Computers (2 assets)';

        $this->assertSame([
            ['Date', 'Account Code', 'Account Name', 'Description', 'Debit', 'Credit'],
            ['2024-03-31', '6101', 'Depreciation Expense - Computers', $description, '300000', ''],
            ['2024-03-31', '1291', 'Accumulated Depreciation - Computers', $description, '', '300000'],
        ], $this->parseCsv($this->downloadedContent($component)));
    }

    public function test_a_posted_journal_is_not_marked_as_draft(): void
    {
        $this->depreciableAsset();
        app(DepreciationRunner::class)->post(DepreciationBook::Commercial, CarbonImmutable::parse('2024-03-01'), User::factory()->create());

        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(DepreciationJournalReport::class)
            ->filterTable('report', ['book' => DepreciationBook::Commercial->value, 'month' => '2024-03-01'])
            ->callAction('exportXlsx')
            ->assertFileDownloaded('depreciation-journal-commercial-2024-03.xlsx');
    }

    public function test_the_journal_offers_no_export_for_a_month_that_was_not_calculated(): void
    {
        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(DepreciationJournalReport::class)
            ->filterTable('report', ['book' => DepreciationBook::Commercial->value, 'month' => '2024-03-01'])
            ->assertActionHidden('exportCsv')
            ->assertActionHidden('exportXlsx');
    }

    private function downloadedContent(Testable $component): string
    {
        return base64_decode((string) data_get($component->effects, 'download.content'));
    }

    /**
     * @return list<list<string>>
     */
    private function parseCsv(string $content): array
    {
        $lines = preg_split('/\r?\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $content)));

        return array_map(fn (string $line): array => str_getcsv($line, escape: ''), $lines);
    }

    /**
     * @return list<list<mixed>>
     */
    private function readXlsx(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();

        return $rows;
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
