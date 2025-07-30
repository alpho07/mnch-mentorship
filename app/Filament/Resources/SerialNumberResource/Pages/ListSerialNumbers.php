<?php
namespace App\Filament\Resources\SerialNumberResource\Pages;

use App\Filament\Resources\SerialNumberResource;
use App\Models\SerialNumber;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSerialNumbers extends ListRecords
{
    protected static string $resource = SerialNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Serial Number')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(SerialNumber::count()),
            'available' => Tab::make('Available')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'available'))
                ->badge(SerialNumber::where('status', 'available')->count())
                ->badgeColor('success'),
            'assigned' => Tab::make('Assigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'assigned'))
                ->badge(SerialNumber::where('status', 'assigned')->count())
                ->badgeColor('info'),
            'in_transit' => Tab::make('In Transit')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'in_transit'))
                ->badge(SerialNumber::where('status', 'in_transit')->count())
                ->badgeColor('warning'),
            'damaged' => Tab::make('Damaged')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'damaged'))
                ->badge(SerialNumber::where('status', 'damaged')->count())
                ->badgeColor('danger'),
            'warranty_expiring' => Tab::make('Warranty Expiring')
                ->modifyQueryUsing(fn (Builder $query) => $query->warrantyExpiring())
                ->badge(SerialNumber::warrantyExpiring()->count())
                ->badgeColor('warning'),
        ];
    }
}