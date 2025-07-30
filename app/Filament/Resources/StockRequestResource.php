<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockRequestResource\Pages;
use App\Filament\Resources\StockRequestResource\RelationManagers;
use App\Models\StockRequest;
use App\Models\Facility;
use App\Models\User;
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

class StockRequestResource extends Resource
{
    protected static ?string $model = StockRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'request_number';
       public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Request Details')
                    ->tabs([
                        Tabs\Tab::make('Basic Information')
                            ->schema([
                                Section::make('Request Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('request_number')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->visibleOn('edit'),

                                        Forms\Components\Select::make('requesting_facility_id')
                                            ->label('Requesting Facility')
                                            ->relationship('requestingFacility', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->default(fn() => auth()->user()->facility_id),

                                        Forms\Components\Select::make('supplying_facility_id')
                                            ->label('Supplying Facility')
                                            ->relationship('supplyingFacility', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Main Store (if not selected)')
                                            ->helperText('Leave empty to request from main store'),

                                        Forms\Components\Select::make('priority')
                                            ->options([
                                                'low' => 'Low',
                                                'normal' => 'Normal',
                                                'high' => 'High',
                                                'urgent' => 'Urgent',
                                                'emergency' => 'Emergency',
                                            ])
                                            ->default('normal')
                                            ->required(),

                                        Forms\Components\DateTimePicker::make('required_by_date')
                                            ->label('Required By')
                                            ->minDate(now())
                                            ->helperText('When do you need these items?'),

                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'submitted' => 'Submitted',
                                                'approved' => 'Approved',
                                                'partially_fulfilled' => 'Partially Fulfilled',
                                                'fulfilled' => 'Fulfilled',
                                                'rejected' => 'Rejected',
                                                'cancelled' => 'Cancelled',
                                            ])
                                            ->default('draft')
                                            ->disabled(fn(?Model $record) => $record && $record->status !== 'draft')
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Justification')
                            ->schema([
                                Forms\Components\Textarea::make('justification')
                                    ->label('Request Justification')
                                    ->required()
                                    ->rows(4)
                                    ->placeholder('Explain why these items are needed...')
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Additional Notes')
                                    ->rows(3)
                                    ->placeholder('Any additional information...')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Approval Information')
                            ->schema([
                                Forms\Components\Select::make('approved_by')
                                    ->label('Approved By')
                                    ->relationship('approvedBy', 'full_name')
                                    ->disabled()
                                    ->visibleOn('edit'),

                                Forms\Components\DateTimePicker::make('approved_date')
                                    ->label('Approval Date')
                                    ->disabled()
                                    ->visibleOn('edit'),

                                Forms\Components\DateTimePicker::make('fulfilled_date')
                                    ->label('Fulfillment Date')
                                    ->disabled()
                                    ->visibleOn('edit'),

                                Forms\Components\TextInput::make('total_estimated_cost')
                                    ->label('Total Estimated Cost')
                                    ->prefix('$')
                                    ->numeric()
                                    ->disabled()
                                    ->visibleOn('edit'),
                            ])
                            ->columns(2)
                            ->visibleOn('edit'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('requestingFacility.name')
                    ->label('Requesting Facility')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('supplyingFacility.name')
                    ->label('Supplying Facility')
                    ->placeholder('Main Store')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn(StockRequest $record): string => $record->priority_color)
                    ->formatStateUsing(fn(string $state): string => $record->priority_name ?? 'Unknown'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(StockRequest $record): string => $record->status_color)
                    ->formatStateUsing(fn(string $state): string => $record->status_name ?? 'Unknown'),

                Tables\Columns\TextColumn::make('total_items')
                    ->label('Items')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_quantity_requested')
                    ->label('Qty Requested')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fulfillment_percentage')
                    ->label('Fulfilled %')
                    ->alignCenter()
                    ->formatStateUsing(fn(float $state): string => number_format($state, 1) . '%')
                    ->color(fn(float $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        $state > 0 => 'info',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('total_estimated_cost')
                    ->label('Est. Cost')
                    ->money()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_overdue')
                    ->label('Overdue')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('required_by_date')
                    ->label('Required By')
                    ->dateTime()
                    ->sortable()
                    ->color(fn(StockRequest $record): string =>
                    $record->is_overdue ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('requestedBy.full_name')
                    ->label('Requested By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('request_date')
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
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                        'partially_fulfilled' => 'Partially Fulfilled',
                        'fulfilled' => 'Fulfilled',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'normal' => 'Normal',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                        'emergency' => 'Emergency',
                    ]),

                SelectFilter::make('requesting_facility')
                    ->relationship('requestingFacility', 'name')
                    ->preload(),

                SelectFilter::make('supplying_facility')
                    ->relationship('supplyingFacility', 'name')
                    ->preload(),

                Filter::make('overdue')
                    ->label('Overdue Requests')
                    ->query(fn(Builder $query): Builder => $query->overdue())
                    ->toggle(),

                Filter::make('pending')
                    ->label('Pending Requests')
                    ->query(fn(Builder $query): Builder => $query->pending())
                    ->toggle(),

                Filter::make('urgent')
                    ->label('Urgent Requests')
                    ->query(fn(Builder $query): Builder => $query->urgent())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(StockRequest $record): bool =>
                    $record->status === 'draft'),

                Tables\Actions\Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn(StockRequest $record): bool =>
                    $record->status === 'draft')
                    ->action(function (StockRequest $record): void {
                        $record->update(['status' => 'submitted']);

                        Notification::make()
                            ->title('Request submitted successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(StockRequest $record): bool =>
                    $record->can_be_approved && auth()->user()->can('approve', $record))
                    ->form([
                        Forms\Components\Repeater::make('approvals')
                            ->label('Item Approvals')
                            ->schema([
                                Forms\Components\TextInput::make('item_name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('quantity_requested')
                                    ->disabled(),
                                Forms\Components\TextInput::make('quantity_approved')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0),
                            ])
                            ->columns(3)
                            ->default(
                                fn(StockRequest $record) =>
                                $record->items->map(fn($item) => [
                                    'id' => $item->id,
                                    'item_name' => $item->inventoryItem->name,
                                    'quantity_requested' => $item->quantity_requested,
                                    'quantity_approved' => $item->quantity_requested,
                                ])->toArray()
                            ),
                    ])
                    ->action(function (StockRequest $record, array $data): void {
                        $approvals = collect($data['approvals'])->pluck('quantity_approved', 'id')->toArray();
                        $record->approve(auth()->user(), $approvals);

                        Notification::make()
                            ->title('Request approved successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(StockRequest $record): bool =>
                    $record->can_be_approved && auth()->user()->can('approve', $record))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (StockRequest $record, array $data): void {
                        $record->reject(auth()->user(), $data['reason']);

                        Notification::make()
                            ->title('Request rejected')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('fulfill')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn(StockRequest $record): bool =>
                    $record->can_be_fulfilled && auth()->user()->can('fulfill', $record))
                    ->url(fn(StockRequest $record): string =>
                    route('filament.admin.resources.stock-requests.fulfill', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete', StockRequest::class)),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Overview')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('request_number')
                                            ->label('Request Number')
                                            ->copyable()
                                            ->size('lg')
                                            ->weight('bold'),

                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn(StockRequest $record): string => $record->status_color),

                                        Infolists\Components\TextEntry::make('priority')
                                            ->badge()
                                            ->color(fn(StockRequest $record): string => $record->priority_color),

                                        Infolists\Components\TextEntry::make('requestingFacility.name')
                                            ->label('Requesting Facility'),

                                        Infolists\Components\TextEntry::make('supplyingFacility.name')
                                            ->label('Supplying Facility')
                                            ->placeholder('Main Store'),
                                    ]),

                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('request_date')
                                            ->dateTime(),

                                        Infolists\Components\TextEntry::make('required_by_date')
                                            ->dateTime()
                                            ->color(fn(StockRequest $record): string =>
                                            $record->is_overdue ? 'danger' : 'gray'),

                                        Infolists\Components\TextEntry::make('approved_date')
                                            ->dateTime()
                                            ->placeholder('Not approved yet'),

                                        Infolists\Components\TextEntry::make('fulfilled_date')
                                            ->dateTime()
                                            ->placeholder('Not fulfilled yet'),
                                    ]),
                                ]),
                        ]),
                    ]),

                Infolists\Components\Section::make('Request Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_items')
                                    ->label('Total Items'),

                                Infolists\Components\TextEntry::make('total_quantity_requested')
                                    ->label('Total Quantity Requested'),

                                Infolists\Components\TextEntry::make('total_quantity_approved')
                                    ->label('Total Quantity Approved'),

                                Infolists\Components\TextEntry::make('fulfillment_percentage')
                                    ->label('Fulfillment Progress')
                                    ->formatStateUsing(fn(float $state): string => number_format($state, 1) . '%')
                                    ->badge()
                                    ->color(fn(float $state): string => match (true) {
                                        $state >= 100 => 'success',
                                        $state >= 50 => 'warning',
                                        $state > 0 => 'info',
                                        default => 'gray'
                                    }),
                            ]),
                    ]),

                Infolists\Components\Section::make('Justification & Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('justification')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No additional notes'),
                    ]),

                Infolists\Components\Section::make('Approval Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('requestedBy.full_name')
                                    ->label('Requested By'),

                                Infolists\Components\TextEntry::make('approvedBy.full_name')
                                    ->label('Approved By')
                                    ->placeholder('Not approved yet'),

                                Infolists\Components\TextEntry::make('total_estimated_cost')
                                    ->label('Estimated Cost')
                                    ->money(),
                            ]),
                    ])
                    ->visible(fn(StockRequest $record): bool =>
                    in_array($record->status, ['approved', 'partially_fulfilled', 'fulfilled'])),
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
            'index' => Pages\ListStockRequests::route('/'),
            'create' => Pages\CreateStockRequest::route('/create'),
            //'view' => Pages\ViewStockRequest::route('/{record}'),
            'edit' => Pages\EditStockRequest::route('/{record}/edit'),
            //'fulfill' => Pages\FulfillStockRequest::route('/{record}/fulfill'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pending()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $urgentCount = static::getModel()::urgent()->count();
        return $urgentCount > 0 ? 'danger' : 'warning';
    }
}
