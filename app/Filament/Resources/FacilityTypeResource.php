<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacilityTypeResource\Pages;
use App\Filament\Resources\FacilityTypeResource\RelationManagers;
use App\Models\FacilityType;
use Dom\Text;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FacilityTypeResource extends Resource
{
    protected static ?string $model = FacilityType::class;
    
    protected static ?string $navigationGroup = 'Organization Units';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacilityTypes::route('/'),
            'create' => Pages\CreateFacilityType::route('/create'),
            'edit' => Pages\EditFacilityType::route('/{record}/edit'),
        ];
    }
}
