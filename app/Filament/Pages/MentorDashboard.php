<?php

namespace App\Filament\Pages;

use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\ClassParticipant;
use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use App\Filament\Resources\MentorshipTrainingResource;

class MentorDashboard extends Page implements HasTable {

    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'My Dashboard';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.mentor-dashboard';

    public function getTitle(): string {
        return 'Mentor Dashboard';
    }

    public function getHeading(): string {
        $user = auth()->user();
        return "Welcome back, {$user->first_name}!";
    }

    public function getSubheading(): ?string {
        $activeCount = Training::where('mentor_id', auth()->id())
                ->where('status', 'active')
                ->count();

        return "You have {$activeCount} active mentorship program(s)";
    }

    protected function getHeaderWidgets(): array {
        return [
            \App\Filament\Widgets\MentorStatsWidget::class,
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                Training::query()
                                ->where('mentor_id', auth()->id())
                                ->with(['program', 'facility'])
                                ->latest()
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('program.name')
                            ->label('Mentorship Program')
                            ->searchable()
                            ->weight('bold')
                            ->icon('heroicon-o-academic-cap')
                            ->description(fn($record) => $record->facility?->name),
                            Tables\Columns\TextColumn::make('dates')
                            ->label('Duration')
                            ->icon('heroicon-o-calendar')
                            ->getStateUsing(fn($record) =>
                                    $record->start_date?->format('M j, Y') . ' - ' .
                                    ($record->end_date?->format('M j, Y') ?? 'Ongoing')
                            ),
                            Tables\Columns\TextColumn::make('classes_count')
                            ->label('Classes')
                            ->icon('heroicon-o-user-group')
                            ->counts('mentorshipClasses')
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('total_mentees')
                            ->label('Total Mentees')
                            ->icon('heroicon-o-users')
                            ->getStateUsing(function ($record) {
                                return ClassParticipant::whereHas('mentorshipClass', function ($query) use ($record) {
                                            $query->where('training_id', $record->id);
                                        })->distinct('user_id')->count();
                            })
                            ->badge()
                            ->color('success'),
                            Tables\Columns\BadgeColumn::make('status')
                            ->colors([
                                'secondary' => 'draft',
                                'success' => 'active',
                                'info' => 'completed',
                                'danger' => 'cancelled',
                            ]),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('view_classes')
                            ->label('Manage')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->color('primary')
                            ->url(fn($record) => MentorshipTrainingResource::getUrl('classes', ['record' => $record])),
                        ])
                        ->emptyStateHeading('No Mentorships Yet')
                        ->emptyStateDescription('You haven\'t been assigned any mentorship programs yet.')
                        ->emptyStateIcon('heroicon-o-academic-cap');
    }

    public static function shouldRegisterNavigation(): bool {
        // Only show for users who are mentors
        return auth()->check() && Training::where('mentor_id', auth()->id())->exists();
    }
}

