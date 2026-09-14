<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Setting;
use App\Services\AssetCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssetCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setMany([
            'asset_code_format' => '{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}',
            'asset_code_sequence_length' => 4,
        ]);
    }

    public function test_it_builds_the_code_from_the_configured_format(): void
    {
        $category = AssetCategory::factory()->create(['prefix' => 'LAP']);
        $branch = Branch::factory()->create(['code' => 'HO']);

        $code = app(AssetCodeGenerator::class)->generate($category, $branch, Carbon::parse('2026-09-14'));

        $this->assertSame('LAP-HO-2609-0001', $code);
    }

    public function test_the_sequence_runs_per_pattern_not_globally(): void
    {
        $laptops = AssetCategory::factory()->create(['prefix' => 'LAP']);
        $chairs = AssetCategory::factory()->create(['prefix' => 'KRS']);
        $branch = Branch::factory()->create(['code' => 'HO']);
        $date = Carbon::parse('2026-09-14');

        $generator = app(AssetCodeGenerator::class);

        $this->assertSame('LAP-HO-2609-0001', $generator->generate($laptops, $branch, $date));
        $this->assertSame('LAP-HO-2609-0002', $generator->generate($laptops, $branch, $date));
        $this->assertSame('KRS-HO-2609-0001', $generator->generate($chairs, $branch, $date));
    }

    public function test_branches_keep_separate_sequences(): void
    {
        $category = AssetCategory::factory()->create(['prefix' => 'LAP']);
        $head = Branch::factory()->create(['code' => 'HO']);
        $bandung = Branch::factory()->create(['code' => 'BDG']);
        $date = Carbon::parse('2026-09-14');

        $generator = app(AssetCodeGenerator::class);

        $this->assertSame('LAP-HO-2609-0001', $generator->generate($category, $head, $date));
        $this->assertSame('LAP-BDG-2609-0001', $generator->generate($category, $bandung, $date));
    }

    public function test_the_sequence_length_follows_the_setting(): void
    {
        Setting::set('asset_code_sequence_length', 6);

        $category = AssetCategory::factory()->create(['prefix' => 'LAP']);
        $branch = Branch::factory()->create(['code' => 'HO']);

        $code = app(AssetCodeGenerator::class)->generate($category, $branch, Carbon::parse('2026-09-14'));

        $this->assertSame('LAP-HO-2609-000001', $code);
    }

    public function test_generated_codes_stay_unique_across_many_assets(): void
    {
        $category = AssetCategory::factory()->create(['prefix' => 'LAP']);
        $branch = Branch::factory()->create(['code' => 'HO']);
        $generator = app(AssetCodeGenerator::class);

        $codes = collect(range(1, 50))
            ->map(fn (): string => $generator->generate($category, $branch, Carbon::parse('2026-09-14')));

        $this->assertCount(50, $codes->unique());
    }
}
