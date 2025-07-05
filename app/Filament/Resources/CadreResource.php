<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CadreResource\Pages;
use App\Models\Cadre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CadreResource extends Resource
{
    protected static ?string $model = Cadre::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Organization Units';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Cadre Name')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Cadre')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->filters([])
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCadres::route('/'),
            'create' => Pages\CreateCadre::route('/create'),
            'edit' => Pages\EditCadre::route('/{record}/edit'),
        ];
    }
}
