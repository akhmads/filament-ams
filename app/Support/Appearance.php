<?php

namespace App\Support;

use App\Models\Setting;
use Filament\Support\Enums\Width;
use Throwable;

/**
 * Panel layout choices an operator can make on the Settings page.
 *
 * The panel reads these through closures at render time, so a saved change shows
 * on the next page load without a deploy or a cache clear.
 */
class Appearance
{
    public const MAX_CONTENT_WIDTH = 'appearance_max_content_width';

    /** What Filament itself falls back to when no width is set. */
    public const DEFAULT_MAX_CONTENT_WIDTH = Width::SevenExtraLarge;

    /**
     * The Width cases worth offering for a page container, narrowest first.
     *
     * Filament's enum also carries 3xs–sm and the min/max/fit/prose/screen cases;
     * those are either far too narrow for a data table or not container widths at
     * all, so they are left out of the picker rather than invented anew.
     *
     * @var array<int, Width>
     */
    private const CONTENT_WIDTHS = [
        Width::ThreeExtraLarge,
        Width::FourExtraLarge,
        Width::FiveExtraLarge,
        Width::SixExtraLarge,
        Width::SevenExtraLarge,
        Width::Full,
    ];

    /**
     * Approximate rendered width, shown so the choice means something.
     *
     * @var array<string, string>
     */
    private const WIDTH_HINTS = [
        '3xl' => '768px',
        '4xl' => '896px',
        '5xl' => '1024px',
        '6xl' => '1152px',
        '7xl' => '1280px',
        'full' => 'full window width',
    ];

    /**
     * @return array<string, string> value => label, for a picker
     */
    public static function maxContentWidthOptions(): array
    {
        $options = [];

        foreach (self::CONTENT_WIDTHS as $width) {
            $label = $width->value.' — '.(self::WIDTH_HINTS[$width->value] ?? $width->value);

            if ($width === self::DEFAULT_MAX_CONTENT_WIDTH) {
                $label .= ' (default)';
            }

            $options[$width->value] = $label;
        }

        return $options;
    }

    /**
     * Falls back to Filament's own default when the setting is unset, holds a
     * value no longer offered, or cannot be read at all — which happens before
     * the settings table has been migrated.
     */
    public static function maxContentWidth(): Width
    {
        try {
            $stored = Setting::get(self::MAX_CONTENT_WIDTH);
        } catch (Throwable) {
            return self::DEFAULT_MAX_CONTENT_WIDTH;
        }

        if (! is_string($stored)) {
            return self::DEFAULT_MAX_CONTENT_WIDTH;
        }

        $width = Width::tryFrom($stored);

        return in_array($width, self::CONTENT_WIDTHS, strict: true)
            ? $width
            : self::DEFAULT_MAX_CONTENT_WIDTH;
    }
}
