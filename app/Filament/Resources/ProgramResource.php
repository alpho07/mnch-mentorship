<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgramResource\Pages;
use App\Filament\Resources\ProgramResource\RelationManagers\ModulesRelationManager;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ProgramResource extends Resource {

    protected static ?string $model = Program::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Curriculum';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'admin', 'division']);
    }

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Program Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->label('Program Name')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->columnSpanFull(),
                                Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(4)
                                ->columnSpanFull(),
                            ])
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->label('Program Name')
                            ->sortable()
                            ->searchable()
                            ->weight('bold'),
//                Tables\Columns\TextColumn::make('description')
//                    ->label('Description')
//                    ->limit(60)
//                    ->wrap(),
                            Tables\Columns\TextColumn::make('module_count')
                            ->label('Modules')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('training_count')
                            ->label('Trainings')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable(),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->filters([
                            Tables\Filters\Filter::make('has_modules')
                            ->label('Has Modules')
                            ->query(fn($query) => $query->withModules()),
                            Tables\Filters\Filter::make('has_trainings')
                            ->label('Has Trainings')
                            ->query(fn($query) => $query->withTrainings()),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make()
                            ->requiresConfirmation(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Infolists\Components\Section::make('Program Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                ->label('Program Name')
                                ->size('lg')
                                ->weight('bold'),
                                Infolists\Components\TextEntry::make('description')
                                ->label('Description')
                                ->prose(),
                            ])
                            ->columns(1),
                            Infolists\Components\Section::make('Statistics')
                            ->schema([
                                Infolists\Components\TextEntry::make('module_count')
                                ->label('Total Modules')
                                ->badge()
                                ->color('info'),
                                Infolists\Components\TextEntry::make('training_count')
                                ->label('Total Trainings')
                                ->badge()
                                ->color('success'),
                                Infolists\Components\TextEntry::make('active_training_count')
                                ->label('Active Trainings')
                                ->badge()
                                ->color('warning'),
                                Infolists\Components\TextEntry::make('total_participants')
                                ->label('Total Participants')
                                ->badge()
                                ->color('primary'),
                            ])
                            ->columns(4),
                            Infolists\Components\Section::make('Metadata')
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(),
                            ])
                            ->columns(2)
                            ->collapsible(),
        ]);
    }

    public static function getRelations(): array {
        return [
                // ModulesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListPrograms::route('/'),
            'create' => Pages\CreateProgram::route('/create'),
            'view' => Pages\ViewProgram::route('/{record}'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }
}
