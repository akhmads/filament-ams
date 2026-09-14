<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageAppearance;
use App\Models\Setting;
use App\Models\User;
use App\Support\Appearance;
use App\Support\Theme;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_palette_falls_back_to_defaults_when_nothing_is_set(): void
    {
        $colors = Theme::colors();

        $this->assertSame(Color::Blue, $colors['primary']);
        $this->assertSame(Color::Zinc, $colors['gray']);
        $this->assertSame(Color::Red, $colors['danger']);
        $this->assertSame(Color::Blue, $colors['info']);
        $this->assertSame(Color::Green, $colors['success']);
        $this->assertSame(Color::Amber, $colors['warning']);
    }

    public function test_a_saved_palette_is_applied(): void
    {
        Setting::set(Theme::PRIMARY, 'Indigo', Appearance::GROUP);
        Setting::set(Theme::SUCCESS, 'Emerald', Appearance::GROUP);

        $colors = Theme::colors();

        $this->assertSame(Color::Indigo, $colors['primary']);
        $this->assertSame(Color::Emerald, $colors['success']);
    }

    public function test_an_unknown_palette_falls_back_to_its_default(): void
    {
        Setting::set(Theme::PRIMARY, 'Rainbow', Appearance::GROUP);

        $this->assertSame(Color::Blue, Theme::colors()['primary']);
    }

    public function test_every_offered_palette_exists_in_filament(): void
    {
        foreach (Theme::PALETTES as $name) {
            $this->assertTrue(defined(Color::class.'::'.$name), "Color::{$name} does not exist");
        }
    }

    public function test_every_default_is_an_offered_palette(): void
    {
        foreach (Theme::DEFAULTS as $key => $name) {
            $this->assertContains($name, Theme::PALETTES, "default for {$key}");
        }
    }

    public function test_the_options_carry_a_colour_swatch(): void
    {
        $options = Theme::optionsHtml();

        // Same palettes as the plain options, but each label is HTML that shows
        // the palette's 500 shade as a swatch next to its name.
        $this->assertSame(array_keys(Theme::options()), array_keys($options));
        $this->assertStringContainsString(Color::Blue[500], $options['Blue']);
        $this->assertStringContainsString('Blue', $options['Blue']);
    }

    public function test_the_panel_uses_the_stored_palette(): void
    {
        Setting::set(Theme::PRIMARY, 'Violet', Appearance::GROUP);

        $this->assertSame(Color::Violet, Filament::getPanel('admin')->getColors()['primary']);
    }

    public function test_the_form_opens_on_the_defaults(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->assertFormSet([
                Theme::PRIMARY => 'Blue',
                Theme::GRAY => 'Zinc',
                Theme::WARNING => 'Amber',
            ]);
    }

    public function test_the_appearance_page_persists_a_chosen_colour(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->fillForm([Theme::PRIMARY => 'Violet'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Violet', Setting::get(Theme::PRIMARY));
        $this->assertSame(Appearance::GROUP, Setting::where('key', Theme::PRIMARY)->value('group'));
    }

    public function test_a_palette_that_is_not_offered_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageAppearance::class)
            ->fillForm([Theme::PRIMARY => 'Rainbow'])
            ->call('save')
            ->assertHasFormErrors([Theme::PRIMARY]);

        $this->assertNull(Setting::get(Theme::PRIMARY));
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
