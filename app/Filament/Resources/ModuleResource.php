<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Models\Module;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Curriculum';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('program_id')
                ->label('Program')
                ->options(Program::all()->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Module Name')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')->label('Description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Module')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('program.name')->label('Program')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(40),
                Tables\Columns\TextColumn::make('created_at')->date(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }
}
