<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\LabelTemplate;
use App\Services\LabelRenderer;
use App\Services\LabelSheetGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LabelPrintingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_qr_encodes_the_public_lookup_url_for_the_asset(): void
    {
        $asset = Asset::factory()->create(['code' => 'LAP-HO-2609-0001']);

        $this->assertSame(route('asset.lookup', ['code' => 'LAP-HO-2609-0001']), $asset->qr_url);
    }

    public function test_it_renders_a_qr_image(): void
    {
        $asset = Asset::factory()->create();

        $dataUri = app(LabelRenderer::class)->qrDataUri($asset);

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
        $this->assertGreaterThan(200, strlen($dataUri));
    }

    public function test_it_renders_a_code128_barcode(): void
    {
        $asset = Asset::factory()->create(['code' => 'LAP-HO-2609-0001']);

        $dataUri = app(LabelRenderer::class)->barcodeDataUri($asset);

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function test_it_produces_a_pdf_for_a_single_label(): void
    {
        $asset = Asset::factory()->create();
        $template = LabelTemplate::factory()->create();

        $pdf = app(LabelSheetGenerator::class)->render(new Collection([$asset]), $template);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_it_produces_a_sheet_for_many_assets_with_both_code_types(): void
    {
        $assets = Asset::factory()->count(5)->create();
        $template = LabelTemplate::factory()->withBarcode()->create(['columns' => 3]);

        $pdf = app(LabelSheetGenerator::class)->render($assets, $template);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf));
    }

    public function test_printing_records_a_trace_on_the_asset(): void
    {
        $assets = Asset::factory()->count(2)->create();

        app(LabelSheetGenerator::class)->markPrinted($assets);

        foreach ($assets as $asset) {
            $asset->refresh();

            $this->assertSame(1, $asset->label_printed_count);
            $this->assertNotNull($asset->label_printed_at);
        }
    }

    public function test_a_roll_template_puts_exactly_one_label_on_each_page(): void
    {
        $assets = Asset::factory()->count(6)->create();
        $template = LabelTemplate::factory()->create([
            'columns' => 1,
            'show_company_name' => true,
            'show_asset_name' => true,
        ]);

        $pdf = app(LabelSheetGenerator::class)->render($assets, $template);

        $this->assertSame(6, $this->pageCount($pdf));
    }

    public function test_a_long_asset_name_does_not_spill_onto_extra_pages(): void
    {
        $assets = Asset::factory()->count(3)->create([
            'name' => 'Laptop Lenovo ThinkPad T14 Gen 4 with Docking Station',
        ]);
        $template = LabelTemplate::factory()->withBarcode()->create([
            'columns' => 1,
            'show_company_name' => true,
            'show_asset_name' => true,
            'show_branch' => true,
        ]);

        $pdf = app(LabelSheetGenerator::class)->render($assets, $template);

        $this->assertSame(3, $this->pageCount($pdf));
    }

    public function test_a_sheet_template_fills_a_grid_before_starting_a_new_page(): void
    {
        $assets = Asset::factory()->count(6)->create();
        $template = LabelTemplate::factory()->create([
            'columns' => 3,
            'width_mm' => 63,
            'height_mm' => 35,
        ]);

        $pdf = app(LabelSheetGenerator::class)->render($assets, $template);

        // 3 columns x 8 rows fit on a single A4 page.
        $this->assertSame(1, $this->pageCount($pdf));
    }

    public function test_a_sheet_template_breaks_to_a_second_page_when_the_grid_is_full(): void
    {
        $assets = Asset::factory()->count(25)->create();
        $template = LabelTemplate::factory()->create([
            'columns' => 3,
            'width_mm' => 63,
            'height_mm' => 35,
        ]);

        $pdf = app(LabelSheetGenerator::class)->render($assets, $template);

        $this->assertSame(2, $this->pageCount($pdf));
    }

    /**
     * The page count is read from the /Count entry in the PDF page tree.
     */
    private function pageCount(string $pdf): int
    {
        preg_match_all('/\/Count (\d+)/', $pdf, $matches);

        return (int) max(array_map('intval', $matches[1] ?: ['0']));
    }

    public function test_the_default_template_is_the_one_flagged_as_default(): void
    {
        LabelTemplate::factory()->create(['is_default' => false]);
        $default = LabelTemplate::factory()->create(['is_default' => true]);

        $this->assertSame($default->id, LabelTemplate::default()->id);
    }

    public function test_only_one_template_can_be_the_default(): void
    {
        $first = LabelTemplate::factory()->create(['is_default' => true]);
        $second = LabelTemplate::factory()->create(['is_default' => true]);

        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue($second->refresh()->is_default);
    }

    public function test_scanning_the_qr_leads_to_the_asset_page(): void
    {
        $asset = Asset::factory()->create(['code' => 'LAP-HO-2609-0001']);

        $this->get(route('asset.lookup', ['code' => $asset->code]))
            ->assertRedirect();
    }

    public function test_an_unknown_code_returns_not_found(): void
    {
        $this->get(route('asset.lookup', ['code' => 'TIDAK-ADA-0001']))
            ->assertNotFound();
    }
}
