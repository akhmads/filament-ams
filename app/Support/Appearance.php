<?php

namespace App\Support;

use App\Models\Setting;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Panel layout choices an operator can make on the Appearance page.
 *
 * The panel reads these through closures at render time, so a saved change shows
 * on the next page load without a deploy or a cache clear.
 */
class Appearance
{
    /** The `settings.group` every appearance key is stored under. */
    public const GROUP = 'appearance';

    public const MAX_CONTENT_WIDTH = 'appearance_max_content_width';

    public const BRAND_LOGO = 'appearance_brand_logo';

    public const BRAND_LOGO_DARK = 'appearance_brand_logo_dark';

    public const BRAND_LOGO_HEIGHT = 'appearance_brand_logo_height';

    public const SPA_MODE = 'appearance_spa_mode';

    public const SPA_PREFETCHING = 'appearance_spa_prefetching';

    /** What Filament itself falls back to when no width is set. */
    public const DEFAULT_MAX_CONTENT_WIDTH = Width::SevenExtraLarge;

    /** Filament's own logo height, in rem. */
    public const DEFAULT_BRAND_LOGO_HEIGHT = 1.5;

    public const MIN_BRAND_LOGO_HEIGHT = 0.5;

    public const MAX_BRAND_LOGO_HEIGHT = 6.0;

    public const LOGO_DISK = 'public';

    public const LOGO_DIRECTORY = 'branding/panel';

    /**
     * Raster formats only. SVG is left out on purpose: it can carry script, and
     * it would be served from this origin under /storage.
     *
     * @var array<int, string>
     */
    public const LOGO_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public const LOGO_MAX_KILOBYTES = 1024;

    /**
     * The Width cases worth offering for a page container, narrowest first.
     *
     * Filament's enum also carries 3xs–2xl and the min/max/fit/prose/screen cases;
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
     * Every appearance key with its default. The settings page and the seeder
     * both read this, so a new option only needs adding here and to the form.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            self::BRAND_LOGO => null,
            self::BRAND_LOGO_DARK => null,
            self::BRAND_LOGO_HEIGHT => self::DEFAULT_BRAND_LOGO_HEIGHT,
            self::MAX_CONTENT_WIDTH => self::DEFAULT_MAX_CONTENT_WIDTH->value,
            self::SPA_MODE => false,
            self::SPA_PREFETCHING => false,
        ];
    }

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

    /**
     * The logo for light mode. When only a dark logo was uploaded it stands in
     * here too, so an upload never silently has no effect.
     */
    public static function brandLogoUrl(): ?string
    {
        return self::logoUrl(self::BRAND_LOGO) ?? self::logoUrl(self::BRAND_LOGO_DARK);
    }

    /**
     * The dark mode logo, or null — in which case Filament reuses the light one.
     */
    public static function darkModeBrandLogoUrl(): ?string
    {
        return self::logoUrl(self::BRAND_LOGO_DARK);
    }

    /**
     * The logo height as a CSS length, e.g. "2.25rem". Anything unset, not a
     * number, or outside the allowed range falls back to Filament's default.
     */
    public static function brandLogoHeight(): string
    {
        try {
            $stored = Setting::get(self::BRAND_LOGO_HEIGHT);
        } catch (Throwable) {
            $stored = null;
        }

        $height = is_numeric($stored) ? (float) $stored : self::DEFAULT_BRAND_LOGO_HEIGHT;

        if ($height < self::MIN_BRAND_LOGO_HEIGHT || $height > self::MAX_BRAND_LOGO_HEIGHT) {
            $height = self::DEFAULT_BRAND_LOGO_HEIGHT;
        }

        return rtrim(rtrim(number_format($height, 2, '.', ''), '0'), '.').'rem';
    }

    /**
     * Whether panel links navigate without full page reloads. Off unless an
     * operator turns it on, matching Filament's own default.
     */
    public static function isSpaMode(): bool
    {
        return self::storedBoolean(self::SPA_MODE);
    }

    /**
     * Whether links start loading on hover, ahead of the click. Only meaningful
     * in SPA mode, so it reads as off whenever SPA mode is off — a prefetch flag
     * left over from an earlier save cannot switch anything on by itself.
     */
    public static function hasSpaPrefetching(): bool
    {
        return self::isSpaMode() && self::storedBoolean(self::SPA_PREFETCHING);
    }

    /**
     * A stored flag as a boolean. Anything unset, unreadable, or not recognisably
     * true or false reads as false.
     */
    private static function storedBoolean(string $key): bool
    {
        try {
            $stored = Setting::get($key);
        } catch (Throwable) {
            return false;
        }

        if (is_bool($stored)) {
            return $stored;
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * A public URL for a stored logo, or null when none is set or its file has
     * gone missing — a broken image in the topbar is worse than the brand name.
     *
     * asset() is used rather than the disk URL because the disk builds its URL
     * from APP_URL, while asset() follows the scheme and host of the request.
     */
    private static function logoUrl(string $key): ?string
    {
        try {
            $path = Setting::get($key);

            if (! is_string($path) || blank($path) || ! Storage::disk(self::LOGO_DISK)->exists($path)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
