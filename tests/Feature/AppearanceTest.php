<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Appearance;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_the_filament_default_when_nothing_is_set(): void
    {
        $this->assertSame(Width::SevenExtraLarge, Appearance::maxContentWidth());
        $this->assertSame(Appearance::DEFAULT_MAX_CONTENT_WIDTH, Appearance::maxContentWidth());
    }

    public function test_it_returns_the_stored_width(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, 'full', 'appearance');

        $this->assertSame(Width::Full, Appearance::maxContentWidth());
    }

    public function test_an_unrecognised_value_falls_back_to_the_default(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, 'not-a-width', 'appearance');

        $this->assertSame(Appearance::DEFAULT_MAX_CONTENT_WIDTH, Appearance::maxContentWidth());
    }

    public function test_a_width_filament_has_but_we_do_not_offer_falls_back(): void
    {
        // 'xs' is a real Width case, but far too narrow to be offered here.
        Setting::set(Appearance::MAX_CONTENT_WIDTH, Width::ExtraSmall->value, 'appearance');

        $this->assertSame(Appearance::DEFAULT_MAX_CONTENT_WIDTH, Appearance::maxContentWidth());
    }

    public function test_every_option_is_a_real_filament_width(): void
    {
        foreach (array_keys(Appearance::maxContentWidthOptions()) as $value) {
            $this->assertNotNull(Width::tryFrom($value), "[{$value}] is not a Filament Width");
        }
    }

    public function test_the_default_option_is_marked_as_such(): void
    {
        $options = Appearance::maxContentWidthOptions();

        $this->assertArrayHasKey(Appearance::DEFAULT_MAX_CONTENT_WIDTH->value, $options);
        $this->assertStringContainsString('(default)', $options[Appearance::DEFAULT_MAX_CONTENT_WIDTH->value]);
    }

    public function test_the_panel_uses_the_stored_width(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, '4xl', 'appearance');

        $this->assertSame(Width::FourExtraLarge, Filament::getPanel('admin')->getMaxContentWidth());
    }

    public function test_saving_the_settings_page_stores_the_chosen_width(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ManageSettings::class)
            ->fillForm([
                'company_name' => 'PT Contoh Sejahtera',
                'asset_code_format' => '{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}',
                'asset_code_sequence_length' => 4,
                'fiscal_year_start_month' => 1,
                Appearance::MAX_CONTENT_WIDTH => 'full',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Width::Full, Appearance::maxContentWidth());
        $this->assertSame('appearance', Setting::where('key', Appearance::MAX_CONTENT_WIDTH)->value('group'));
    }

    public function test_the_width_is_read_back_into_the_form(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        Setting::set(Appearance::MAX_CONTENT_WIDTH, '5xl', 'appearance');

        Livewire::actingAs($admin)
            ->test(ManageSettings::class)
            ->assertFormSet([Appearance::MAX_CONTENT_WIDTH => '5xl']);
    }
}
