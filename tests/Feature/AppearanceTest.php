<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageAppearance;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Appearance;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Appearance::LOGO_DISK);
    }

    public function test_it_falls_back_to_the_filament_default_when_nothing_is_set(): void
    {
        $this->assertSame(Width::SevenExtraLarge, Appearance::maxContentWidth());
        $this->assertSame(Appearance::DEFAULT_MAX_CONTENT_WIDTH, Appearance::maxContentWidth());
    }

    public function test_it_returns_the_stored_width(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, 'full', Appearance::GROUP);

        $this->assertSame(Width::Full, Appearance::maxContentWidth());
    }

    public function test_an_unrecognised_value_falls_back_to_the_default(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, 'not-a-width', Appearance::GROUP);

        $this->assertSame(Appearance::DEFAULT_MAX_CONTENT_WIDTH, Appearance::maxContentWidth());
    }

    public function test_a_width_filament_has_but_we_do_not_offer_falls_back(): void
    {
        // 'xs' is a real Width case, but far too narrow to be offered here.
        Setting::set(Appearance::MAX_CONTENT_WIDTH, Width::ExtraSmall->value, Appearance::GROUP);

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

    public function test_every_default_is_itself_a_valid_choice(): void
    {
        $this->assertArrayHasKey(
            Appearance::defaults()[Appearance::MAX_CONTENT_WIDTH],
            Appearance::maxContentWidthOptions(),
        );
    }

    public function test_the_panel_uses_the_stored_width(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, '4xl', Appearance::GROUP);

        $this->assertSame(Width::FourExtraLarge, Filament::getPanel('admin')->getMaxContentWidth());
    }

    public function test_the_appearance_page_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(ManageAppearance::getUrl())
            ->assertSuccessful()
            ->assertSee('Save Appearance');
    }

    public function test_the_form_opens_on_the_default_when_nothing_is_stored(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->assertFormSet([Appearance::MAX_CONTENT_WIDTH => Appearance::DEFAULT_MAX_CONTENT_WIDTH->value]);
    }

    public function test_the_stored_width_is_read_back_into_the_form(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, '5xl', Appearance::GROUP);

        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->assertFormSet([Appearance::MAX_CONTENT_WIDTH => '5xl']);
    }

    public function test_saving_stores_the_width_under_the_appearance_group(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->fillForm([Appearance::MAX_CONTENT_WIDTH => 'full'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(ManageAppearance::getUrl());

        $this->assertSame(Width::Full, Appearance::maxContentWidth());
        $this->assertSame(Appearance::GROUP, Setting::where('key', Appearance::MAX_CONTENT_WIDTH)->value('group'));
    }

    public function test_saving_general_settings_leaves_appearance_untouched(): void
    {
        Setting::set(Appearance::MAX_CONTENT_WIDTH, '3xl', Appearance::GROUP);

        Livewire::actingAs($this->admin())
            ->test(ManageSettings::class)
            ->fillForm([
                'company_name' => 'PT Contoh Sejahtera',
                'asset_code_format' => '{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}',
                'asset_code_sequence_length' => 4,
                'fiscal_year_start_month' => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Width::ThreeExtraLarge, Appearance::maxContentWidth());
    }

    public function test_there_is_no_brand_logo_until_one_is_uploaded(): void
    {
        $this->assertNull(Appearance::brandLogoUrl());
        $this->assertNull(Appearance::darkModeBrandLogoUrl());
    }

    public function test_a_stored_logo_resolves_to_a_public_url(): void
    {
        $path = $this->storeLogo('light.png');
        Setting::set(Appearance::BRAND_LOGO, $path, Appearance::GROUP);

        $this->assertSame(asset('storage/'.$path), Appearance::brandLogoUrl());
    }

    public function test_a_logo_whose_file_is_missing_is_ignored(): void
    {
        Setting::set(Appearance::BRAND_LOGO, 'branding/panel/gone.png', Appearance::GROUP);

        $this->assertNull(Appearance::brandLogoUrl());
    }

    public function test_a_light_logo_alone_leaves_dark_mode_to_reuse_it(): void
    {
        Setting::set(Appearance::BRAND_LOGO, $this->storeLogo('light.png'), Appearance::GROUP);

        $this->assertNotNull(Appearance::brandLogoUrl());
        $this->assertNull(Appearance::darkModeBrandLogoUrl());
    }

    public function test_a_dark_logo_alone_is_used_in_both_modes(): void
    {
        $path = $this->storeLogo('dark.png');
        Setting::set(Appearance::BRAND_LOGO_DARK, $path, Appearance::GROUP);

        $this->assertSame(asset('storage/'.$path), Appearance::brandLogoUrl());
        $this->assertSame(asset('storage/'.$path), Appearance::darkModeBrandLogoUrl());
    }

    public function test_each_mode_uses_its_own_logo_when_both_exist(): void
    {
        $light = $this->storeLogo('light.png');
        $dark = $this->storeLogo('dark.png');
        Setting::setMany([Appearance::BRAND_LOGO => $light, Appearance::BRAND_LOGO_DARK => $dark], Appearance::GROUP);

        $this->assertSame(asset('storage/'.$light), Appearance::brandLogoUrl());
        $this->assertSame(asset('storage/'.$dark), Appearance::darkModeBrandLogoUrl());
    }

    public function test_the_logo_height_defaults_to_filaments_own(): void
    {
        $this->assertSame('1.5rem', Appearance::brandLogoHeight());
    }

    public function test_the_logo_height_is_rendered_in_rem_without_trailing_zeros(): void
    {
        Setting::set(Appearance::BRAND_LOGO_HEIGHT, 2.25, Appearance::GROUP);
        $this->assertSame('2.25rem', Appearance::brandLogoHeight());

        Setting::set(Appearance::BRAND_LOGO_HEIGHT, '2', Appearance::GROUP);
        $this->assertSame('2rem', Appearance::brandLogoHeight());
    }

    public function test_an_unusable_logo_height_falls_back_to_the_default(): void
    {
        foreach (['abc', 0.1, 50] as $bad) {
            Setting::set(Appearance::BRAND_LOGO_HEIGHT, $bad, Appearance::GROUP);

            $this->assertSame('1.5rem', Appearance::brandLogoHeight(), 'value: '.json_encode($bad));
        }
    }

    public function test_the_panel_uses_the_stored_logos_and_height(): void
    {
        $light = $this->storeLogo('light.png');
        $dark = $this->storeLogo('dark.png');
        Setting::setMany([
            Appearance::BRAND_LOGO => $light,
            Appearance::BRAND_LOGO_DARK => $dark,
            Appearance::BRAND_LOGO_HEIGHT => 2.5,
        ], Appearance::GROUP);

        $panel = Filament::getPanel('admin');

        $this->assertSame(asset('storage/'.$light), $panel->getBrandLogo());
        $this->assertSame(asset('storage/'.$dark), $panel->getDarkModeBrandLogo());
        $this->assertSame('2.5rem', $panel->getBrandLogoHeight());
    }

    public function test_uploading_logos_and_height_stores_them_under_the_appearance_group(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->fillForm([
                Appearance::BRAND_LOGO => UploadedFile::fake()->image('light.png', 400, 100),
                Appearance::BRAND_LOGO_DARK => UploadedFile::fake()->image('dark.png', 400, 100),
                Appearance::BRAND_LOGO_HEIGHT => 2,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        foreach ([Appearance::BRAND_LOGO, Appearance::BRAND_LOGO_DARK] as $key) {
            $path = Setting::get($key);

            $this->assertIsString($path);
            $this->assertStringStartsWith(Appearance::LOGO_DIRECTORY.'/', $path);
            Storage::disk(Appearance::LOGO_DISK)->assertExists($path);
            $this->assertSame(Appearance::GROUP, Setting::where('key', $key)->value('group'));
        }

        $this->assertSame('2rem', Appearance::brandLogoHeight());
    }

    public function test_a_file_that_is_not_an_allowed_image_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->fillForm([
                Appearance::BRAND_LOGO => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
            ])
            ->call('save')
            ->assertHasFormErrors([Appearance::BRAND_LOGO]);

        $this->assertNull(Setting::get(Appearance::BRAND_LOGO));
    }

    public function test_a_logo_height_outside_the_range_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->fillForm([Appearance::BRAND_LOGO_HEIGHT => 10])
            ->call('save')
            ->assertHasFormErrors([Appearance::BRAND_LOGO_HEIGHT => 'max']);
    }

    public function test_spa_mode_is_off_until_turned_on(): void
    {
        $this->assertFalse(Appearance::isSpaMode());
        $this->assertFalse(Appearance::hasSpaPrefetching());
    }

    public function test_a_stored_spa_flag_is_read_as_a_boolean(): void
    {
        Setting::set(Appearance::SPA_MODE, true, Appearance::GROUP);
        $this->assertTrue(Appearance::isSpaMode());

        Setting::set(Appearance::SPA_MODE, false, Appearance::GROUP);
        $this->assertFalse(Appearance::isSpaMode());
    }

    public function test_an_unrecognised_spa_flag_reads_as_off(): void
    {
        Setting::set(Appearance::SPA_MODE, 'maybe', Appearance::GROUP);

        $this->assertFalse(Appearance::isSpaMode());
    }

    public function test_prefetching_stays_off_while_spa_mode_is_off(): void
    {
        Setting::setMany([Appearance::SPA_MODE => false, Appearance::SPA_PREFETCHING => true], Appearance::GROUP);

        $this->assertFalse(Appearance::hasSpaPrefetching());
    }

    public function test_prefetching_is_on_when_both_flags_are_on(): void
    {
        Setting::setMany([Appearance::SPA_MODE => true, Appearance::SPA_PREFETCHING => true], Appearance::GROUP);

        $this->assertTrue(Appearance::hasSpaPrefetching());
    }

    public function test_the_panel_uses_the_stored_spa_settings(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse($panel->hasSpaMode());

        Setting::setMany([Appearance::SPA_MODE => true, Appearance::SPA_PREFETCHING => true], Appearance::GROUP);

        $this->assertTrue($panel->hasSpaMode());
        $this->assertTrue($panel->hasSpaPrefetching());
    }

    public function test_saving_turns_spa_mode_on_under_the_appearance_group(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->assertFormSet([Appearance::SPA_MODE => false])
            ->fillForm([Appearance::SPA_MODE => true, Appearance::SPA_PREFETCHING => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Appearance::isSpaMode());
        $this->assertTrue(Appearance::hasSpaPrefetching());
        $this->assertSame(Appearance::GROUP, Setting::where('key', Appearance::SPA_MODE)->value('group'));
    }

    public function test_turning_spa_mode_off_also_turns_prefetching_off(): void
    {
        Setting::setMany([Appearance::SPA_MODE => true, Appearance::SPA_PREFETCHING => true], Appearance::GROUP);

        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->fillForm([Appearance::SPA_MODE => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(Appearance::isSpaMode());
        $this->assertFalse(Appearance::hasSpaPrefetching());
    }

    private function storeLogo(string $name): string
    {
        return UploadedFile::fake()->image($name, 400, 100)
            ->store(Appearance::LOGO_DIRECTORY, Appearance::LOGO_DISK);
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
