<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TreePathTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_full_path_of_a_nested_category(): void
    {
        $root = AssetCategory::factory()->create(['name' => 'IT Equipment']);
        $child = AssetCategory::factory()->create(['name' => 'Laptop', 'parent_id' => $root->id]);
        $grandchild = AssetCategory::factory()->create(['name' => 'Ultrabook', 'parent_id' => $child->id]);

        $this->assertSame('IT Equipment', $root->full_name);
        $this->assertSame('IT Equipment › Laptop', $child->full_name);
        $this->assertSame('IT Equipment › Laptop › Ultrabook', $grandchild->full_name);
    }

    public function test_it_renders_the_full_path_of_a_nested_location(): void
    {
        $building = Location::factory()->create(['name' => 'Main Building']);
        $room = Location::factory()->create([
            'branch_id' => $building->branch_id,
            'parent_id' => $building->id,
            'name' => 'IT Room',
        ]);

        $this->assertSame('Main Building › IT Room', $room->full_name);
    }

    public function test_the_path_never_lazy_loads(): void
    {
        $root = AssetCategory::factory()->create(['name' => 'IT Equipment']);
        AssetCategory::factory()->create(['name' => 'Laptop', 'parent_id' => $root->id]);

        Model::preventLazyLoading(true);

        // Membaca path dari hasil query biasa tidak boleh memicu pelanggaran.
        $paths = AssetCategory::query()->get()->map(fn (AssetCategory $c): string => $c->full_name);

        $this->assertCount(2, $paths);
    }

    public function test_labelling_many_nodes_costs_one_extra_query(): void
    {
        $root = AssetCategory::factory()->create(['name' => 'IT Equipment']);

        foreach (range(1, 20) as $i) {
            AssetCategory::factory()->create(['name' => "Child {$i}", 'parent_id' => $root->id]);
        }

        AssetCategory::forgetTreePath();

        DB::enableQueryLog();
        AssetCategory::query()->get()->each(fn (AssetCategory $c): string => $c->full_name);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Satu query untuk daftarnya, satu untuk peta pohon — bukan satu per baris.
        $this->assertSame(2, $queries);
    }

    public function test_a_rename_is_reflected_within_the_same_request(): void
    {
        $root = AssetCategory::factory()->create(['name' => 'IT Equipment']);
        $child = AssetCategory::factory()->create(['name' => 'Laptop', 'parent_id' => $root->id]);

        $this->assertSame('IT Equipment › Laptop', $child->full_name);

        $root->update(['name' => 'Technology']);

        $this->assertSame('Technology › Laptop', $child->fresh()->full_name);
    }

    public function test_an_eager_loaded_ancestors_relation_is_used_when_present(): void
    {
        $root = AssetCategory::factory()->create(['name' => 'IT Equipment']);
        AssetCategory::factory()->create(['name' => 'Laptop', 'parent_id' => $root->id]);

        AssetCategory::forgetTreePath();

        $child = AssetCategory::query()->with('ancestors')->where('name', 'Laptop')->firstOrFail();

        DB::enableQueryLog();
        $path = $child->full_name;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame('IT Equipment › Laptop', $path);
        $this->assertSame(0, $queries, 'Relasi yang sudah dimuat tidak boleh memicu query peta pohon.');
    }

    public function test_an_unsaved_node_falls_back_to_its_own_name(): void
    {
        $category = new AssetCategory(['name' => 'Draft Category']);

        $this->assertSame('Draft Category', $category->full_name);
    }
}
