<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockTransferResource\Pages;
use App\Filament\Resources\StockTransferResource\RelationManagers;
use App\Models\StockTransfer;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'transfer_number';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Transfer Details')
                    ->tabs([
                        Tabs\Tab::make('Basic Information')
                            ->schema([
                                Section::make('Transfer Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('transfer_number')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->visibleOn('edit'),

                                        Forms\Components\Select::make('from_facility_id')
                                            ->label('From Facility')
                                            ->relationship('fromFacility', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Main Store (if not selected)')
                                            ->helperText('Leave empty if transferring from main store'),

                                        Forms\Components\Select::make('to_facility_id')
                                            ->label('To Facility')
                                            ->relationship('toFacility', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'pending' => 'Pending Approval',
                                                'approved' => 'Approved',
                                                'in_transit' => 'In Transit',
                                                'delivered' => 'Delivered',
                                                'received' => 'Received',
                                                'cancelled' => 'Cancelled',
                                            ])
                                            ->default('draft')
                                            ->disabled(fn(?Model $record) =>
                                            $record && !in_array($record->status, ['draft', 'pending']))
                                            ->required(),

                                        Forms\Components\DateTimePicker::make('expected_arrival_date')
                                            ->label('Expected Arrival')
                                            ->minDate(now())
                                            ->helperText('When should this transfer arrive?'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Shipping Information')
                            ->schema([
                                Section::make('Transport Details')
                                    ->schema([
                                        Forms\Components\Select::make('transport_method')
                                            ->label('Transport Method')
                                            ->options([
                                                'road' => 'Road Transport',
                                                'air' => 'Air Transport',
                                                'rail' => 'Rail Transport',
                                                'courier' => 'Courier Service',
                                                'hand_delivery' => 'Hand Delivery',
                                                'other' => 'Other',
                                            ])
                                            ->placeholder('Select transport method'),

                                        Forms\Components\TextInput::make('tracking_number')
                                            ->label('Tracking Number')
                                            ->placeholder('Enter tracking number if available'),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Transfer Notes')
                                            ->rows(3)
                                            ->placeholder('Any special instructions or notes...')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Status Information')
                            ->schema([
                                Section::make('Transfer Timeline')
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('transfer_date')
                                            ->label('Transfer Date')
                                            ->disabled()
                                            ->visibleOn('edit'),

                                        Forms\Components\DateTimePicker::make('actual_arrival_date')
                                            ->label('Actual Arrival Date')
                                            ->disabled()
                                            ->visibleOn('edit'),

                                        Forms\Components\Select::make('approved_by')
                                            ->label('Approved By')
                                            ->relationship('approvedBy', 'full_name')
                                            ->disabled()
                                            ->visibleOn('edit'),

                                        Forms\Components\Select::make('received_by')
                                            ->label('Received By')
                                            ->relationship('receivedBy', 'full_name')
                                            ->disabled()
                                            ->visibleOn('edit'),

                                        Forms\Components\TextInput::make('total_value')
                                            ->label('Total Value')
                                            ->prefix('$')
                                            ->numeric()
                                            ->disabled()
                                            ->visibleOn('edit'),

                                        Forms\Components\TextInput::make('total_items')
                                            ->label('Total Items')
                                            ->numeric()
                                            ->disabled()
                                            ->visibleOn('edit'),
                                    ])
                                    ->columns(2)
                                    ->visibleOn('edit'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->label('Transfer #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('fromFacility.name')
                    ->label('From')
                    ->placeholder('Main Store')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('toFacility.name')
                    ->label('To')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(StockTransfer $record): string => $record->status_color)
                    ->formatStateUsing(fn(string $state): string => $record->status_name ?? 'Unknown'),

                Tables\Columns\TextColumn::make('total_items_count')
                    ->label('Items')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_quantity')
                    ->label('Received Qty')
                    ->alignCenter()
                    ->badge()
                    ->color(fn(StockTransfer $record): string => match (true) {
                        $record->received_quantity >= $record->total_quantity => 'success',
                        $record->received_quantity > 0 => 'warning',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Value')
                    ->money()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transport_method')
                    ->label('Transport')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(?string $state): string =>
                    $state ? (StockTransfer::TRANSPORT_METHODS[$state] ?? 'Unknown') : 'Not Set'),

                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->copyable()
                    ->placeholder('No tracking'),

                Tables\Columns\IconColumn::make('is_overdue')
                    ->label('Overdue')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('expected_arrival_date')
                    ->label('Expected Arrival')
                    ->dateTime()
                    ->sortable()
                    ->color(fn(StockTransfer $record): string =>
                    $record->is_overdue ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('days_in_transit')
                    ->label('Transit Days')
                    ->alignCenter()
                    ->placeholder('N/A')
                    ->color(fn(?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state <= 3 => 'success',
                        $state <= 7 => 'warning',
                        default => 'danger'
                    }),

                Tables\Columns\TextColumn::make('delivery_performance')
                    ->label('Performance')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'on_time' => 'success',
                        'delayed' => 'danger',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'on_time' => 'On Time',
                        'delayed' => 'Delayed',
                        default => 'Pending'
                    }),

                Tables\Columns\TextColumn::make('initiatedBy.full_name')
                    ->label('Initiated By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('transfer_date')
                    ->label('Shipped Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending Approval',
                        'approved' => 'Approved',
                        'in_transit' => 'In Transit',
                        'delivered' => 'Delivered',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('from_facility')
                    ->relationship('fromFacility', 'name')
                    ->preload()
                    ->label('From Facility'),

                SelectFilter::make('to_facility')
                    ->relationship('toFacility', 'name')
                    ->preload()
                    ->label('To Facility'),

                SelectFilter::make('transport_method')
                    ->options([
                        'road' => 'Road Transport',
                        'air' => 'Air Transport',
                        'rail' => 'Rail Transport',
                        'courier' => 'Courier Service',
                        'hand_delivery' => 'Hand Delivery',
                        'other' => 'Other',
                    ]),

                Filter::make('overdue')
                    ->label('Overdue Transfers')
                    ->query(fn(Builder $query): Builder => $query->overdue())
                    ->toggle(),

                Filter::make('in_transit')
                    ->label('In Transit')
                    ->query(fn(Builder $query): Builder => $query->inTransit())
                    ->toggle(),

                Filter::make('pending')
                    ->label('Pending Transfers')
                    ->query(fn(Builder $query): Builder => $query->pending())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(StockTransfer $record): bool =>
                    in_array($record->status, ['draft', 'pending'])),

                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(StockTransfer $record): bool =>
                    $record->can_be_approved && auth()->user()->can('approve', $record))
                    ->action(function (StockTransfer $record): void {
                        $record->approve(auth()->user());

                        Notification::make()
                            ->title('Transfer approved successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('ship')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will create outbound transactions and update stock levels.')
                    ->visible(fn(StockTransfer $record): bool =>
                    $record->can_be_shipped && auth()->user()->can('ship', $record))
                    ->action(function (StockTransfer $record): void {
                        $record->ship(auth()->user());

                        Notification::make()
                            ->title('Transfer shipped successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('receive')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('info')
                    ->visible(fn(StockTransfer $record): bool =>
                    $record->can_be_received && auth()->user()->can('receive', $record))
                    ->url(fn(StockTransfer $record): string =>
                    route('filament.admin.resources.stock-transfers.receive', $record)),

                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(StockTransfer $record): bool =>
                    !in_array($record->status, ['received', 'cancelled']))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (StockTransfer $record, array $data): void {
                        $record->cancel($data['reason']);

                        Notification::make()
                            ->title('Transfer cancelled')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete', StockTransfer::class)),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transfer Overview')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('transfer_number')
                                            ->label('Transfer Number')
                                            ->copyable()
                                            ->size('lg')
                                            ->weight('bold'),

                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn(StockTransfer $record): string => $record->status_color),

                                        Infolists\Components\TextEntry::make('fromFacility.name')
                                            ->label('From Facility')
                                            ->placeholder('Main Store'),

                                        Infolists\Components\TextEntry::make('toFacility.name')
                                            ->label('To Facility'),

                                        Infolists\Components\TextEntry::make('transport_method_name')
                                            ->label('Transport Method'),

                                        Infolists\Components\TextEntry::make('tracking_number')
                                            ->label('Tracking Number')
                                            ->copyable()
                                            ->placeholder('No tracking number'),
                                    ]),

                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('transfer_date')
                                            ->label('Transfer Date')
                                            ->dateTime()
                                            ->placeholder('Not shipped yet'),

                                        Infolists\Components\TextEntry::make('expected_arrival_date')
                                            ->label('Expected Arrival')
                                            ->dateTime()
                                            ->color(fn(StockTransfer $record): string =>
                                            $record->is_overdue ? 'danger' : 'gray'),

                                        Infolists\Components\TextEntry::make('actual_arrival_date')
                                            ->label('Actual Arrival')
                                            ->dateTime()
                                            ->placeholder('Not received yet'),

                                        Infolists\Components\TextEntry::make('days_in_transit')
                                            ->label('Days in Transit')
                                            ->placeholder('N/A'),

                                        Infolists\Components\TextEntry::make('delivery_performance')
                                            ->label('Delivery Performance')
                                            ->badge()
                                            ->color(fn(?string $state): string => match ($state) {
                                                'on_time' => 'success',
                                                'delayed' => 'danger',
                                                default => 'gray'
                                            }),
                                    ]),
                                ]),
                        ]),
                    ]),

                Infolists\Components\Section::make('Transfer Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_items_count')
                                    ->label('Total Items'),

                                Infolists\Components\TextEntry::make('total_quantity')
                                    ->label('Total Quantity'),

                                Infolists\Components\TextEntry::make('received_quantity')
                                    ->label('Received Quantity')
                                    ->badge()
                                    ->color(fn(StockTransfer $record): string => match (true) {
                                        $record->received_quantity >= $record->total_quantity => 'success',
                                        $record->received_quantity > 0 => 'warning',
                                        default => 'gray'
                                    }),

                                Infolists\Components\TextEntry::make('total_value')
                                    ->label('Total Value')
                                    ->money(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Personnel Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('initiatedBy.full_name')
                                    ->label('Initiated By'),

                                Infolists\Components\TextEntry::make('approvedBy.full_name')
                                    ->label('Approved By')
                                    ->placeholder('Not approved yet'),

                                Infolists\Components\TextEntry::make('receivedBy.full_name')
                                    ->label('Received By')
                                    ->placeholder('Not received yet'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])
                    ->visible(fn(StockTransfer $record): bool => !empty($record->notes)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //RelationManagers\ItemsRelationManager::class,
            //RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            //'view' => Pages\ViewStockTransfer::route('/{record}'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
            //'receive' => Pages\ReceiveStockTransfer::route('/{record}/receive'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pending()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $overdueCount = static::getModel()::overdue()->count();
        return $overdueCount > 0 ? 'danger' : 'warning';
    }
}
