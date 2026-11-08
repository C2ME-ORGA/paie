<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Builder;
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
            ->authGuard('web')
            ->login()
            ->strictAuthorization()
            ->plugins([
                ActivityLogPlugin::make()
                    ->configureTableUsing(function (Table $table): Table {
                        $table->getColumn('causer.name')?->visible(false);

                        return $table
                            ->pushColumns([
                                TextColumn::make('origin')
                                    ->label('Origine')
                                    ->badge()
                                    ->state(fn ($record): ?string => data_get($record->properties, 'origin'))
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'user' => 'Utilisateur',
                                        'system' => 'Système',
                                        'automated' => 'Automatisé',
                                        default => 'Inconnue',
                                    })
                                    ->color(fn (?string $state): string => match ($state) {
                                        'user' => 'success',
                                        'system' => 'gray',
                                        'automated' => 'info',
                                        default => 'warning',
                                    }),
                                TextColumn::make('actor_display')
                                    ->label('Auteur')
                                    ->state(fn ($record): string => $record->causer->name
                                        ?? ($record->causer_id === null ? 'Système' : 'Auteur supprimé')),
                            ])
                            ->pushFilters([
                                SelectFilter::make('origin')
                                    ->label('Origine')
                                    ->options([
                                        'user' => 'Utilisateur',
                                        'system' => 'Système',
                                        'automated' => 'Automatisé',
                                    ])
                                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                                        filled($data['value'] ?? null),
                                        fn (Builder $query): Builder => $query->where(
                                            'properties->origin',
                                            $data['value'],
                                        ),
                                    )),
                            ]);
                    }),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
