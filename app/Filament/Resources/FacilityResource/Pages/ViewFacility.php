<?php

// File: App\Filament\Resources\FacilityResource\Pages\ViewFacility.php
namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFacility extends ViewRecord
{
    protected static string $resource = FacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_on_map')
                ->label('View on Map')
                ->icon('heroicon-o-map')
                ->color('info')
                ->visible(fn (): bool => $this->record->coordinates !== null)
                ->url(fn (): string => 
                    "https://maps.google.com/maps?q={$this->record->lat},{$this->record->long}"
                )
                ->openUrlInNewTab(),
            
            Actions\Action::make('manage_staff')
                ->label('Manage Staff')
                ->icon('heroicon-o-users')
                ->color('success')
                ->url(fn (): string => 
                    route('filament.admin.resources.users.index', [
                        'tableFilters[facility_id][value]' => $this->record->id
                    ])
                ),
            
            Actions\Action::make('training_history')
                ->label('Training History')
                ->icon('heroicon-o-academic-cap')
                ->color('warning')
                ->action(function (): void {
                    $this->notify('info', 'Training history will be available once training management is implemented');
                }),
            
            Actions\EditAction::make(),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will delete the facility and all associated data. This action cannot be undone.'),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\Shared\RelationManagers\UsersRelationManager::class,
        ];
    }
}