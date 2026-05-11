<?php

namespace App\Providers\Filament;

use App\Filament\Pages\TrainingDashboard;
use App\Http\Livewire\Dashboard\TrainingDash;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Navigation\MenuItem;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\HtmlString;
use Filament\View\PanelsRenderHook;
use App\Livewire\Auth\{
    CustomLogin,
    CustomRegister,
    CustomRequestPasswordReset
};

class AdminPanelProvider extends PanelProvider {

    public function panel(Panel $panel): Panel {
        return $panel
                        ->default()
                        ->id('admin')
                        ->path('admin')
                        //->login()
                        ->login(CustomLogin::class)
                        ->registration(CustomRegister::class)
                       // ->passwordResetRequest(CustomRequestPasswordReset::class)
                        ->passwordReset(CustomRequestPasswordReset::class)
                        ->colors([
                            'primary' => Color::Cyan,
                        ])
                        ->darkMode(true)
                        ->renderHook(
                            PanelsRenderHook::HEAD_END,
                            fn (): HtmlString => new HtmlString(
                                '<link rel="stylesheet" href="' . asset('css/filament-admin-theme.css') . '?v=' . filemtime(public_path('css/filament-admin-theme.css')) . '">'
                            ),
                        )
                        ->navigationGroups([
                            'Dashboards',
                            'Training Management',
                            'Indicator Catalog',
                            'knowledge Base',
                            'Reporting',
                            'Curriculum',
                            'Organization Units',
                            'Inventory',
                            'Report Management',
                            'Reports & Analytics',
                        ])
                        ->userMenuItems([
                            MenuItem::make()
                                ->label('Analytics Dashboard')
                                ->icon('heroicon-o-chart-bar')
                                ->url('/analytics/dashboard?mode=assessment'),
                        ])
                        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
                        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
                        ->pages([
                        ])
                        ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
                        /* ->widgets([
                          Widgets\AccountWidget::class,
                          Widgets\FilamentInfoWidget::class,
                          ]) */
                        ->middleware([
                            EncryptCookies::class,
                            AddQueuedCookiesToResponse::class,
                            StartSession::class,
                            AuthenticateSession::class,
                            ShareErrorsFromSession::class,
                            VerifyCsrfToken::class,
                            SubstituteBindings::class,
                            DisableBladeIconComponents::class,
                            DispatchServingFilamentEvent::class,
                        ])
                        ->plugins([
                            FilamentShieldPlugin::make(),
                        ])
                        ->authMiddleware([
                            Authenticate::class,
        ]);
    }
}
