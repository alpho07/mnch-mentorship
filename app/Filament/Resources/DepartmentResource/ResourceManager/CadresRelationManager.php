<?php

namespace App\Filament\Resources\DepartmentResource\RelationManagers;

use App\Models\Cadre;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CadresRelationManager extends RelationManager
{
    protected static string $relationship = 'cadres'; // This won't work directly
    protected static ?string $recordTitleAttribute = 'name';

    // Override the relationship query to get cadres with users in this department
    protected function getTableQuery(): Builder
    {
        return Cadre::whereHas('users', function ($query) {
            $query->where('department_id', $this->ownerRecord->id);
        });
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cadre Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Cadre Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(Cadre::class, 'name', ignoreRecord: true)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cadre Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Cadre $record): ?string => $record->description),
                
                Tables\Columns\TextColumn::make('users_in_department_count')
                    ->label('Users in This Department')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (Cadre $record): int {
                        return $record->users()
                            ->where('department_id', $this->ownerRecord->id)
                            ->count();
                    }),
                
                Tables\Columns\TextColumn::make('active_users_in_department')
                    ->label('Active Users')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function (Cadre $record): int {
                        return $record->users()
                            ->where('department_id', $this->ownerRecord->id)
                            ->where('status', 'active')
                            ->count();
                    }),
                
                Tables\Columns\TextColumn::make('user_count')
                    ->label('Total Users (System)')
                    ->badge()
                    ->color('gray')
                    ->description('All departments'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('has_active_users')
                    ->label('Has Active Users in Department')
                    ->query(function ($query) {
                        return $query->whereHas('users', function ($q) {
                            $q->where('department_id', $this->ownerRecord->id)
                              ->where('status', 'active');
                        });
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_cadre')
                    ->label('Create New Cadre')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Cadre Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(Cadre::class, 'name'),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function (array $data): void {
                        Cadre::create($data);
                        
                        Notification::make()
                            ->title('Cadre created')
                            ->body('New cadre has been created successfully.')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('assign_existing_cadre')
                    ->label('Assign Existing Cadre')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('cadre_id')
                            ->label('Select Cadre')
                            ->options(function () {
                                // Get cadres that don't have users in this department yet
                                return Cadre::whereDoesntHave('users', function ($query) {
                                    $query->where('department_id', $this->ownerRecord->id);
                                })->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),
                        
                        Forms\Components\Select::make('users')
                            ->label('Select Users to Assign')
                            ->options(function () {
                                return User::where('department_id', $this->ownerRecord->id)
                                    ->whereNull('cadre_id')
                                    ->get()
                                    ->pluck('full_name', 'id');
                            })
                            ->multiple()
                            ->searchable()
                            ->helperText('Select users from this department to assign to the cadre'),
                    ])
                    ->action(function (array $data): void {
                        if (!empty($data['users'])) {
                            User::whereIn('id', $data['users'])
                                ->update(['cadre_id' => $data['cadre_id']]);
                            
                            $cadre = Cadre::find($data['cadre_id']);
                            $count = count($data['users']);
                            
                            Notification::make()
                                ->title('Users assigned')
                                ->body("{$count} users assigned to {$cadre->name}")
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Cadre $record): string => 
                        \App\Filament\Resources\CadreResource::getUrl('view', ['record' => $record])
                    )
                    ->visible(fn (): bool => class_exists(\App\Filament\Resources\CadreResource::class)),
                
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('view_users')
                    ->label('View Department Users')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->action(function (Cadre $record): void {
                        $users = $record->users()
                            ->where('department_id', $this->ownerRecord->id)
                            ->with(['facility', 'roles'])
                            ->get();
                        
                        $this->dispatch('show-cadre-users-modal', [
                            'cadre' => $record->name,
                            'department' => $this->ownerRecord->name,
                            'users' => $users->toArray()
                        ]);
                    }),
                
                Tables\Actions\Action::make('reassign_users')
                    ->label('Reassign Users')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('new_cadre_id')
                            ->label('New Cadre')
                            ->options(Cadre::where('id', '!=', fn (Cadre $record) => $record->id)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (Cadre $record, array $data): void {
                        $usersReassigned = User::where('department_id', $this->ownerRecord->id)
                            ->where('cadre_id', $record->id)
                            ->update(['cadre_id' => $data['new_cadre_id']]);
                        
                        $newCadre = Cadre::find($data['new_cadre_id']);
                        
                        Notification::make()
                            ->title('Users reassigned')
                            ->body("{$usersReassigned} users moved from {$record->name} to {$newCadre->name}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->visible(function (Cadre $record): bool {
                        return $record->users()
                            ->where('department_id', $this->ownerRecord->id)
                            ->count() > 0;
                    }),
            ])
            ->emptyStateHeading('No cadres with users in this department')
            ->emptyStateDescription('Create a new cadre or assign existing cadres to users in this department.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}