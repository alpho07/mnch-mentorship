<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingOverviewResource\Pages;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Program;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrainingOverviewResource extends Resource
{
    protected static ?string $model = Training::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?string $navigationLabel = 'Training Overview';
    protected static ?string $slug = 'training-overview';
   
 

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(static::getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Training Type')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('program_names')
                    ->label('Program(s)')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('trainings_count')
                    ->label('No of Trainings')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('facilities_count')
                    ->label('No of Facilities')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('participants_count')
                    ->label('No of Participants')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('latest_training_date')
                    ->label('Latest Training')
                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('M-Y') : '-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('program')
                    ->label('Program')
                    ->options(Program::pluck('name', 'id')),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('to')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $q, $date): Builder =>
                                $q->whereDate('start_date', '>=', $date)
                            )
                            ->when(
                                $data['to'],
                                fn(Builder $q, $date): Builder =>
                                $q->whereDate('start_date', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_trainings')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(
                        fn($record): string =>
                        TrainingResource::getUrl('index', ['tableFilters' => [
                            'title' => ['value' => $record->title]
                        ]])
                    )
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([])
            ->defaultSort('trainings_count', 'desc')
            ->paginated(false); // Show all programs without pagination
    }

    protected static function getTableQuery(): Builder
    {
        return Training::query()
            ->select([
                \DB::raw('MD5(title) as id'), // Create a unique ID based on title
                'title',
                \DB::raw('COUNT(*) as trainings_count'),
                \DB::raw('COUNT(DISTINCT facility_id) as facilities_count'),
                \DB::raw('MAX(start_date) as latest_training_date'),
                \DB::raw('GROUP_CONCAT(DISTINCT programs.name SEPARATOR ", ") as program_names'),
                \DB::raw('(SELECT COUNT(*) FROM training_participants WHERE training_participants.training_id IN (SELECT id FROM trainings t2 WHERE t2.title = trainings.title)) as participants_count')
            ])
            ->leftJoin('programs', 'trainings.program_id', '=', 'programs.id')
            ->groupBy('title')
            ->having('trainings_count', '>', 0)
            ->orderBy('trainings_count', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingOverview::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // This is a read-only overview
    }
}
