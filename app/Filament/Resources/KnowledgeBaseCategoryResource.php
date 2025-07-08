<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KnowledgeBaseCategoryResource\Pages;
use App\Models\KnowledgeBaseCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class KnowledgeBaseCategoryResource extends Resource
{
    protected static ?string $model = KnowledgeBaseCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Knowledge Base';
    protected static ?string $label = 'Category';
    protected static ?string $pluralLabel = 'Categories';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->unique(),
                Forms\Components\TextInput::make('icon')
                    ->label('Icon (Heroicon class, e.g. heroicon-o-book)')
                    ->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('icon')->icon(fn ($record) => $record->icon ?: 'heroicon-o-book'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKnowledgeBaseCategories::route('/'),
            'create' => Pages\CreateKnowledgeBaseCategory::route('/create'),
            'edit' => Pages\EditKnowledgeBaseCategory::route('/{record}/edit'),
        ];
    }
}
