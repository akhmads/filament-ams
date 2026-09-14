<?php

namespace App\Support;

use App\Models\Setting;
use Filament\Support\Colors\Color;
use Throwable;

/**
 * The panel colour palette, chosen by an operator on the Appearance page.
 *
 * Each slot (primary, gray, danger, …) stores the name of a bundled Filament
 * palette. Filament turns the resolved palettes into CSS variables at render, so
 * a saved change takes effect on the next request with no asset rebuild.
 *
 * Follows the Theme class in the lastmile project, with flat setting keys: the
 * settings forms here read a dot in a field name as nesting.
 */
class Theme
{
    public const PRIMARY = 'theme_primary';

    public const GRAY = 'theme_gray';

    public const DANGER = 'theme_danger';

    public const INFO = 'theme_info';

    public const SUCCESS = 'theme_success';

    public const WARNING = 'theme_warning';

    /**
     * Setting => the palette used until one is chosen. These are Filament's own
     * defaults, except primary, which this panel has always shown in Blue.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        self::PRIMARY => 'Blue',
        self::GRAY => 'Zinc',
        self::DANGER => 'Red',
        self::INFO => 'Blue',
        self::SUCCESS => 'Green',
        self::WARNING => 'Amber',
    ];

    /**
     * Setting => the panel colour key it drives.
     *
     * @var array<string, string>
     */
    private const SLOTS = [
        self::PRIMARY => 'primary',
        self::GRAY => 'gray',
        self::DANGER => 'danger',
        self::INFO => 'info',
        self::SUCCESS => 'success',
        self::WARNING => 'warning',
    ];

    /**
     * The palettes an operator may pick from — every palette Filament bundles,
     * neutrals first. Doubles as the allow-list that guards the constant()
     * lookup in colors().
     *
     * @var list<string>
     */
    public const PALETTES = [
        'Slate', 'Gray', 'Zinc', 'Neutral', 'Stone', 'Mauve', 'Olive', 'Mist', 'Taupe',
        'Red', 'Orange', 'Amber', 'Yellow', 'Lime', 'Green', 'Emerald',
        'Teal', 'Cyan', 'Sky', 'Blue', 'Indigo', 'Violet', 'Purple',
        'Fuchsia', 'Pink', 'Rose',
    ];

    /**
     * Options for a Select: palette name keyed to itself.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(self::PALETTES, self::PALETTES);
    }

    /**
     * The same options, but each label carries a colour swatch (the palette's
     * 500 shade) so the choice can be seen, not just read. Use with a Select's
     * ->allowHtml(). The markup is built from a fixed allow-list and Filament's
     * own colour constants, never user input, so it is safe to render as HTML.
     *
     * @return array<string, string>
     */
    public static function optionsHtml(): array
    {
        $options = [];

        foreach (self::PALETTES as $name) {
            /** @var array<int, string> $palette */
            $palette = constant(Color::class.'::'.$name);

            $options[$name] = sprintf(
                '<span style="display:inline-flex;align-items:center;gap:.5rem;">'
                .'<span style="width:.85rem;height:.85rem;border-radius:.25rem;background:%s;'
                .'box-shadow:inset 0 0 0 1px rgba(0,0,0,.15);"></span>%s</span>',
                $palette[500] ?? '#9ca3af',
                $name,
            );
        }

        return $options;
    }

    /**
     * The colour map handed to the panel's ->colors(). Any slot left unset or
     * pointing at an unknown palette falls back to its default; if settings
     * cannot be read at all (e.g. before the table is migrated) every slot does.
     *
     * @return array<string, array<int, string>>
     */
    public static function colors(): array
    {
        $colors = [];

        foreach (self::SLOTS as $key => $slot) {
            /** @var array<int, string> $palette */
            $palette = constant(Color::class.'::'.self::resolveName($key));

            $colors[$slot] = $palette;
        }

        return $colors;
    }

    private static function resolveName(string $key): string
    {
        $default = self::DEFAULTS[$key];

        try {
            $name = Setting::get($key, $default);
        } catch (Throwable) {
            return $default;
        }

        return in_array($name, self::PALETTES, strict: true) ? $name : $default;
    }
}
