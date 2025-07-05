<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubcountyResource\Pages;
use App\Models\Subcounty;
use App\Models\County;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubcountyResource extends Resource
{
    protected static ?string $model = Subcounty::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Organization Units';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('Subcounty Name')
                    ->maxLength(255),
                Forms\Components\Select::make('county_id')
                    ->label('County')
                    ->options(County::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Subcounty Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('county.name')->label('County')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('county_id')
                    ->label('County')
                    ->options(County::all()->pluck('name', 'id'))
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubcounties::route('/'),
            'create' => Pages\CreateSubcounty::route('/create'),
            'edit' => Pages\EditSubcounty::route('/{record}/edit'),
        ];
    }
}
