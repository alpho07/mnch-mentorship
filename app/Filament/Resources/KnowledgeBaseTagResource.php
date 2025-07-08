<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KnowledgeBaseTagResource\Pages;
use App\Models\KnowledgeBaseTag;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class KnowledgeBaseTagResource extends Resource
{
    protected static ?string $model = KnowledgeBaseTag::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Knowledge Base';
    protected static ?string $label = 'Tag';
    protected static ?string $pluralLabel = 'Tags';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required() ->unique(ignoreRecord: true),
                Forms\Components\ColorPicker::make('color')->label('Tag Color')->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\ColorColumn::make('color')->label('Color'),
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
            'index' => Pages\ListKnowledgeBaseTags::route('/'),
            'create' => Pages\CreateKnowledgeBaseTag::route('/create'),
            'edit' => Pages\EditKnowledgeBaseTag::route('/{record}/edit'),
        ];
    }
}
