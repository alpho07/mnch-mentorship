<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentSectionResource\Pages;
use App\Filament\Resources\AssessmentSectionResource\RelationManagers;
use App\Models\AssessmentSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssessmentSectionResource extends Resource
{
    protected static ?string $model = AssessmentSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Assessment Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Sections';
    
    public static function shouldRegisterNavigation(): bool
    {
        return !auth()->user()->hasRole('Assessor');
    }

    public static function canAccess(): bool
    {
        return !auth()->user()->hasRole('Assessor');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('assessment_type_id')
                                    ->label('Assessment Type')
                                    ->relationship('assessmentType', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('name')
                                    ->label('Section Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., 1.0 GENERAL INFORMATION')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->maxLength(255)
                                    ->placeholder('e.g., SECTION_1')
                                    ->alphaDash(),

                                Forms\Components\TextInput::make('order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpan(2),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('assessment_type.name')
                    ->label('Assessment Type')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Section Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Questions')
                    ->counts('questions')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('assessment_type_id')
                    ->label('Assessment Type')
                    ->relationship('assessmentType', 'name')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentSections::route('/'),
            'create' => Pages\CreateAssessmentSection::route('/create'),
           // 'view' => Pages\ViewAssessmentSection::route('/{record}'),
            'edit' => Pages\EditAssessmentSection::route('/{record}/edit'),
        ];
    }
}