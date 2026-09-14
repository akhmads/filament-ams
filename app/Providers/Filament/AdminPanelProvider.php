<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AssetsByCategoryChart;
use App\Filament\Widgets\AssetsByStatusChart;
use App\Filament\Widgets\AssetStatsOverview;
use App\Filament\Widgets\AttentionNeededTable;
use App\Support\Appearance;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('AMS')
            // Chosen on Settings → Appearance. Read through a closure so the panel
            // picks up a change on the next page load, without a deploy.
            ->maxContentWidth(fn (): Width => Appearance::maxContentWidth())
            ->colors([
                'primary' => Color::Blue,
            ])
            // Filament rejects icons on a group when its items carry icons too,
            // so the icons stay on the individual resources.
            ->navigationGroups([
                NavigationGroup::make('Aset'),
                NavigationGroup::make('Master Data')->collapsed(),
                NavigationGroup::make('Pengaturan')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                AssetStatsOverview::class,
                AssetsByStatusChart::class,
                AssetsByCategoryChart::class,
                AttentionNeededTable::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
