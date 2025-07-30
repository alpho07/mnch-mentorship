<?php

namespace App\Filament\Resources\SerialNumberResource\RelationManagers;

use App\Models\SerialNumberTracking;
use App\Models\Facility;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrackingHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'trackingHistory';
    protected static ?string $title = 'Tracking History';
    protected static ?string $modelLabel = 'Tracking Record';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tracking Details')
                    ->schema([
                        Forms\Components\Select::make('action')
                            ->options([
                                'created' => 'Item Created',
                                'moved' => 'Location Changed',
                                'assigned' => 'Assigned to User',
                                'unassigned' => 'Unassigned from User',
                                'damaged' => 'Marked as Damaged',
                                'repaired' => 'Repaired',
                                'retired' => 'Retired from Service',
                                'status_changed' => 'Status Changed',
                                'maintenance' => 'Maintenance Performed',
                                'inspection' => 'Inspection Completed',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('from_location_type')
                            ->label('From Location Type')
                            ->options([
                                'main_store' => 'Main Store',
                                'facility' => 'Facility',
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('action') === 'moved'),

                        Forms\Components\Select::make('from_location_id')
                            ->label('From Facility')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool =>
                                $get('action') === 'moved' && $get('from_location_type') === 'facility'),

                        Forms\Components\Select::make('to_location_type')
                            ->label('To Location Type')
                            ->options([
                                'main_store' => 'Main Store',
                                'facility' => 'Facility',
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('action') === 'moved')
                            ->required(fn (Forms\Get $get): bool => $get('action') === 'moved'),

                        Forms\Components\Select::make('to_location_id')
                            ->label('To Facility')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool =>
                                $get('action') === 'moved' && $get('to_location_type') === 'facility')
                            ->required(fn (Forms\Get $get): bool =>
                                $get('action') === 'moved' && $get('to_location_type') === 'facility'),

                        Forms\Components\Select::make('from_user_id')
                            ->label('From User')
                            ->options(fn (): array => User::pluck('full_name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => $get('action') === 'unassigned'),

                        Forms\Components\Select::make('to_user_id')
                            ->label('To User')
                            ->options(fn (): array => User::pluck('full_name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => $get('action') === 'assigned')
                            ->required(fn (Forms\Get $get): bool => $get('action') === 'assigned'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('GPS Coordinates')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001),

                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Describe what happened...')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Data')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action_description')
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'created' => 'success',
                        'moved' => 'info',
                        'assigned' => 'warning',
                        'unassigned' => 'gray',
                        'damaged' => 'danger',
                        'repaired' => 'success',
                        'retired' => 'gray',
                        'status_changed' => 'info',
                        'maintenance' => 'warning',
                        'inspection' => 'info',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'created' => 'Created',
                        'moved' => 'Moved',
                        'assigned' => 'Assigned',
                        'unassigned' => 'Unassigned',
                        'damaged' => 'Damaged',
                        'repaired' => 'Repaired',
                        'retired' => 'Retired',
                        'status_changed' => 'Status Changed',
                        'maintenance' => 'Maintenance',
                        'inspection' => 'Inspection',
                        default => ucfirst($state)
                    }),

                Tables\Columns\TextColumn::make('action_description')
                    ->label('Description')
                    ->wrap()
                    ->getStateUsing(fn (SerialNumberTracking $record): string => $record->action_description),

                Tables\Columns\TextColumn::make('from_location_name')
                    ->label('From')
                    ->placeholder('N/A')
                    ->wrap()
                    ->getStateUsing(fn (SerialNumberTracking $record): string => $record->from_location_name),

                Tables\Columns\TextColumn::make('to_location_name')
                    ->label('To')
                    ->placeholder('N/A')
                    ->wrap()
                    ->getStateUsing(fn (SerialNumberTracking $record): string => $record->to_location_name),

                Tables\Columns\TextColumn::make('fromUser.full_name')
                    ->label('From User')
                    ->placeholder('N/A')
                    ->wrap(),

                Tables\Columns\TextColumn::make('toUser.full_name')
                    ->label('To User')
                    ->placeholder('N/A')
                    ->wrap(),

                Tables\Columns\TextColumn::make('trackedBy.full_name')
                    ->label('Tracked By')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('coordinates')
                    ->label('GPS')
                    ->boolean()
                    ->getStateUsing(fn (SerialNumberTracking $record): bool =>
                        !empty($record->coordinates))
                    ->trueIcon('heroicon-o-map-pin')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'moved' => 'Moved',
                        'assigned' => 'Assigned',
                        'unassigned' => 'Unassigned',
                        'damaged' => 'Damaged',
                        'repaired' => 'Repaired',
                        'retired' => 'Retired',
                        'status_changed' => 'Status Changed',
                        'maintenance' => 'Maintenance',
                        'inspection' => 'Inspection',
                    ]),

                Tables\Filters\SelectFilter::make('tracked_by')
                    ->relationship('trackedBy', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Activity')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereDate('created_at', today()))
                    ->toggle(),

                Tables\Filters\Filter::make('this_week')
                    ->label('This Week\'s Activity')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereBetween('created_at', [
                            now()->startOfWeek(),
                            now()->endOfWeek()
                        ]))
                    ->toggle(),

                Tables\Filters\Filter::make('has_gps')
                    ->label('Has GPS Coordinates')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNotNull('latitude')->whereNotNull('longitude'))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Add Tracking Record')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tracked_by'] = auth()->id();
                        return $data;
                    }),

                Tables\Actions\Action::make('quick_location_update')
                    ->label('Quick Location Update')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('location_type')
                            ->options([
                                'main_store' => 'Main Store',
                                'facility' => 'Facility',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('location_id')
                            ->label('Facility')
                            ->options(fn (): array => Facility::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => $get('location_type') === 'facility')
                            ->required(fn (Forms\Get $get): bool => $get('location_type') === 'facility'),

                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001),

                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Reason for location change...')
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        $serialNumber = $this->getOwnerRecord();

                        // Create tracking record
                        $serialNumber->trackingHistory()->create([
                            'action' => 'moved',
                            'from_location_id' => $serialNumber->current_location_id,
                            'from_location_type' => $serialNumber->current_location_type,
                            'to_location_id' => $data['location_type'] === 'facility' ? $data['location_id'] : null,
                            'to_location_type' => $data['location_type'],
                            'latitude' => $data['latitude'] ?? null,
                            'longitude' => $data['longitude'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'tracked_by' => auth()->id(),
                        ]);

                        // Update serial number location
                        $serialNumber->updateLocation(
                            $data['location_type'] === 'facility' ? $data['location_id'] : null,
                            $data['location_type'],
                            $data['latitude'] ?? null,
                            $data['longitude'] ?? null
                        );

                        Notification::make()
                            ->title('Location updated successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn (SerialNumberTracking $record): string =>
                        "Tracking Record - {$record->action_description}"),

                Tables\Actions\EditAction::make()
                    ->visible(fn (SerialNumberTracking $record): bool =>
                        $record->tracked_by === auth()->id() || auth()->user()->can('edit', $record)),

                Tables\Actions\Action::make('view_on_map')
                    ->label('View on Map')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->visible(fn (SerialNumberTracking $record): bool =>
                        !empty($record->coordinates))
                    ->url(fn (SerialNumberTracking $record): string =>
                        "https://www.google.com/maps?q={$record->latitude},{$record->longitude}")
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (SerialNumberTracking $record): bool =>
                        $record->tracked_by === auth()->id() || auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->can('delete', SerialNumberTracking::class)),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No tracking history')
            ->emptyStateDescription('No tracking records have been created for this item yet.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
