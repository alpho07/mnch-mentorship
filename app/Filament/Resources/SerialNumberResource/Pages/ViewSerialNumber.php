<?php
namespace App\Filament\Resources\SerialNumberResource\Pages;

use App\Filament\Resources\SerialNumberResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;

class ViewSerialNumber extends ViewRecord
{
    protected static string $resource = SerialNumberResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->serial_number;
    }

    public function getSubheading(): string
    {
        return $this->getRecord()->inventoryItem->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Serial Number Details')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\Group::make([
                                    Components\TextEntry::make('serial_number')
                                        ->label('Serial Number')
                                        ->size('lg')
                                        ->weight(FontWeight::Bold)
                                        ->copyable(),
                                    Components\TextEntry::make('tag_number')
                                        ->label('Tag Number')
                                        ->badge()
                                        ->visible(fn ($record) => $record->tag_number),
                                    Components\TextEntry::make('inventoryItem.name')
                                        ->label('Item')
                                        ->size('lg'),
                                ]),
                                Components\Group::make([
                                    Components\TextEntry::make('status')
                                        ->badge()
                                        ->color(fn ($record) => $record->status_badge_color),
                                    Components\TextEntry::make('condition')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'excellent' => 'success',
                                            'good' => 'info',
                                            'fair' => 'warning',
                                            'poor' => 'danger',
                                        }),
                                    Components\TextEntry::make('warranty_status')
                                        ->label('Warranty Status')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'active' => 'success',
                                            'expiring_soon' => 'warning',
                                            'expired' => 'danger',
                                            'no_warranty' => 'gray',
                                        }),
                                ]),
                                Components\Group::make([
                                    Components\TextEntry::make('current_location_name')
                                        ->label('Current Location')
                                        ->badge()
                                        ->color('primary'),
                                    Components\TextEntry::make('assignedToUser.name')
                                        ->label('Assigned To')
                                        ->visible(fn ($record) => $record->assigned_to_user_id),
                                    Components\TextEntry::make('last_tracked_at')
                                        ->label('Last Tracked')
                                        ->dateTime(),
                                ]),
                            ]),
                    ]),

                Components\Section::make('Dates')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('acquisition_date')
                                    ->label('Acquisition Date')
                                    ->date(),
                                Components\TextEntry::make('warranty_expiry_date')
                                    ->label('Warranty Expires')
                                    ->date()
                                    ->color(fn ($record) => $record->is_warranty_expired ? 'danger' : null),
                                Components\TextEntry::make('created_at')
                                    ->label('Registered')
                                    ->dateTime(),
                            ]),
                    ]),

                Components\Section::make('GPS Coordinates')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('latitude')
                                    ->copyable(),
                                Components\TextEntry::make('longitude')
                                    ->copyable(),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->latitude && $record->longitude)
                    ->collapsible(),

                Components\Section::make('Notes')
                    ->schema([
                        Components\TextEntry::make('notes')
                            ->prose(),
                    ])
                    ->visible(fn ($record) => $record->notes)
                    ->collapsible(),
            ]);
    }
}
