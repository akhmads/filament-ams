<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_back_a_value_that_was_written(): void
    {
        Setting::set('company_name', 'PT Contoh Sejahtera');

        $this->assertSame('PT Contoh Sejahtera', Setting::get('company_name'));
    }

    public function test_it_returns_the_default_for_a_key_that_was_never_set(): void
    {
        $this->assertSame('bawaan', Setting::get('tidak_ada', 'bawaan'));
        $this->assertNull(Setting::get('tidak_ada'));
    }

    public function test_writing_takes_effect_immediately_without_clearing_anything(): void
    {
        Setting::set('asset_code_sequence_length', 4);
        $this->assertSame(4, Setting::get('asset_code_sequence_length'));

        // The point of a per-request memo: write then read gives the new value
        // straight away, with no cache to invalidate first.
        Setting::set('asset_code_sequence_length', 6);

        $this->assertSame(6, Setting::get('asset_code_sequence_length'));
    }

    public function test_set_many_writes_every_value_in_one_go(): void
    {
        Setting::setMany([
            'company_name' => 'PT Satu',
            'company_city' => 'Jakarta',
        ]);

        $this->assertSame('PT Satu', Setting::get('company_name'));
        $this->assertSame('Jakarta', Setting::get('company_city'));
    }

    public function test_repeated_reads_hit_the_database_only_once_per_request(): void
    {
        Setting::setMany(['company_name' => 'PT Contoh', 'company_city' => 'Jakarta']);
        Setting::flush();

        DB::enableQueryLog();

        Setting::get('company_name');
        Setting::get('company_city');
        Setting::get('company_name');

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'settings'));

        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }

    public function test_a_value_changed_outside_the_class_is_seen_by_the_next_request(): void
    {
        Setting::set('company_name', 'PT Lama');
        $this->assertSame('PT Lama', Setting::get('company_name'));

        // A manual fix in SQL, as a data migration would do.
        DB::table('settings')->where('key', 'company_name')->update(['value' => json_encode('PT Baru')]);

        // The next request starts with an empty memo.
        Setting::flush();

        $this->assertSame('PT Baru', Setting::get('company_name'));
    }

    public function test_changing_a_setting_is_recorded_in_the_audit_trail(): void
    {
        Setting::set('asset_code_format', '{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}');
        Setting::set('asset_code_format', '{BRANCH}/{CATEGORY}/{SEQ}');

        $activity = Activity::query()
            ->where('log_name', 'setting')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('updated', $activity->event);

        // In activitylog v5 the diff lives in the `attribute_changes` column,
        // no longer in `properties` as in earlier versions.
        $this->assertSame('{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}', $activity->attribute_changes['old']['value']);
        $this->assertSame('{BRANCH}/{CATEGORY}/{SEQ}', $activity->attribute_changes['attributes']['value']);
    }

    public function test_rewriting_the_same_value_is_not_recorded_as_a_change(): void
    {
        Setting::set('company_name', 'PT Contoh');
        $before = Activity::where('log_name', 'setting')->count();

        Setting::set('company_name', 'PT Contoh');

        $this->assertSame($before, Activity::where('log_name', 'setting')->count());
    }
}
