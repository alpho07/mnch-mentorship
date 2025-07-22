<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IndicatorResource\Pages;
use App\Models\Indicator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IndicatorResource extends Resource
{
    protected static ?string $model = Indicator::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Report Management';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $context, $state, Forms\Set $set) =>
                                $context === 'create' ? $set('code', str(str($state)->slug())->upper()) : null),

                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                            //->alpha_dash()
                            //->uppercase(),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('calculation_type')
                            ->required()
                            ->options([
                                'percentage' => 'Percentage (%)',
                                'count' => 'Count',
                                'rate' => 'Rate (per 1000)',
                                'ratio' => 'Ratio',
                            ]),

                        Forms\Components\TextInput::make('target_value')
                            ->numeric()
                            ->suffix(fn (Forms\Get $get): string => match ($get('calculation_type')) {
                                'percentage' => '%',
                                'rate' => 'per 1000',
                                default => '',
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Calculation Details')
                    ->schema([
                        Forms\Components\Textarea::make('numerator_description')
                            ->required()
                            ->label('Numerator Description')
                            ->helperText('Describe what should be counted in the numerator')
                            ->rows(3),

                        Forms\Components\Textarea::make('denominator_description')
                            ->label('Denominator Description')
                            ->helperText('Describe what should be counted in the denominator (leave empty for count-type indicators)')
                            ->rows(3)
                            ->hidden(fn (Forms\Get $get): bool => $get('calculation_type') === 'count'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Data Source')
                    ->schema([
                        Forms\Components\Textarea::make('source_document')
                            ->label('Source Documents')
                            ->helperText('List the registers, forms, or documents where this data can be found')
                            ->rows(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('calculation_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'success',
                        'count' => 'info',
                        'rate' => 'warning',
                        'ratio' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('target_value')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record): string =>
                        $state ? $state . match ($record->calculation_type) {
                            'percentage' => '%',
                            'rate' => ' per 1000',
                            default => '',
                        } : '-'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('calculation_type')
                    ->options([
                        'percentage' => 'Percentage',
                        'count' => 'Count',
                        'rate' => 'Rate',
                        'ratio' => 'Ratio',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListIndicators::route('/'),
            'create' => Pages\CreateIndicator::route('/create'),
            'view' => Pages\ViewIndicator::route('/{record}'),
            'edit' => Pages\EditIndicator::route('/{record}/edit'),
        ];
    }
}
