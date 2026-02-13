<?php

namespace App\Filament\Pages;

use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\ClassParticipant;
use App\Models\MentorshipCoMentor;
use App\Models\ClassAttendance;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use App\Filament\Resources\MentorshipTrainingResource;
use Illuminate\Support\Facades\DB;

class MentorDashboard extends Page implements HasTable {

    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'My Dashboard';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.mentor-dashboard';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'facility_mentor', 'division']);
    }
    
    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'facility_mentor', 'division']);
    }


    public function getTitle(): string {
        return 'Mentor Dashboard';
    }

    public function getHeading(): string {
        $user = auth()->user();
        return "Welcome back, {$user->first_name}!";
    }

    public function getSubheading(): ?string {
        $stats = $this->getDashboardStats();
        return "You have {$stats['active_mentorships']} active mentorship(s) with {$stats['total_mentees']} total mentees";
    }

    /**
     * Get all training IDs where user is lead mentor OR accepted co-mentor.
     */
    private function getMyTrainingIds(): array {
        $userId = auth()->id();

        $asLead = Training::where('mentor_id', $userId)
                ->where('type', 'facility_mentorship')
                ->pluck('id');

        $asCoMentor = MentorshipCoMentor::where('user_id', $userId)
                ->where('status', 'accepted')
                ->pluck('training_id');

        return $asLead->merge($asCoMentor)->unique()->toArray();
    }

    /**
     * Compute accurate dashboard stats.
     */
    public function getDashboardStats(): array {
        $trainingIds = $this->getMyTrainingIds();

        $totalMentorships = count($trainingIds);

        $activeClasses = MentorshipClass::whereIn('training_id', $trainingIds)
                ->where('status', 'active')
                ->count();

        $totalMentees = ClassParticipant::whereHas('mentorshipClass', function ($q) use ($trainingIds) {
                    $q->whereIn('training_id', $trainingIds);
                })->distinct('user_id')->count('user_id');

        $totalModules = ClassModule::whereHas('mentorshipClass', function ($q) use ($trainingIds) {
                    $q->whereIn('training_id', $trainingIds);
                })->count();

        $completedModules = ClassModule::whereHas('mentorshipClass', function ($q) use ($trainingIds) {
                    $q->whereIn('training_id', $trainingIds);
                })->where('status', 'completed')->count();

        $totalSessions = ClassSession::whereHas('classModule.mentorshipClass', function ($q) use ($trainingIds) {
                    $q->whereIn('training_id', $trainingIds);
                })->count();

        $completedSessions = ClassSession::whereHas('classModule.mentorshipClass', function ($q) use ($trainingIds) {
                    $q->whereIn('training_id', $trainingIds);
                })->where('status', 'completed')->count();

        // Attendance rate from class_attendances table
        $attendanceRecords = ClassAttendance::whereHas('mentorshipClass', function ($q) use ($trainingIds) {
                    $q->whereIn('training_id', $trainingIds);
                })->count();

        return [
            'total_mentorships' => $totalMentorships,
            'active_mentorships' => Training::whereIn('id', $trainingIds)->where('status', 'active')->count(),
            'active_classes' => $activeClasses,
            'total_mentees' => $totalMentees,
            'total_modules' => $totalModules,
            'completed_modules' => $completedModules,
            'module_completion_rate' => $totalModules > 0 ? round(($completedModules / $totalModules) * 100, 1) : 0,
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'attendance_records' => $attendanceRecords,
        ];
    }

    public function table(Table $table): Table {
        $trainingIds = $this->getMyTrainingIds();

        return $table
                        ->query(
                                Training::query()
                                ->whereIn('id', $trainingIds)
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
                            Tables\Columns\TextColumn::make('role')
                            ->label('My Role')
                            ->getStateUsing(function ($record) {
                                if ($record->mentor_id === auth()->id()) {
                                    return 'Lead Mentor';
                                }
                                return 'Co-Mentor';
                            })
                            ->badge()
                            ->color(fn($state) => $state === 'Lead Mentor' ? 'success' : 'info'),
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
                                        })->distinct('user_id')->count('user_id');
                            })
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('module_progress')
                            ->label('Modules')
                            ->getStateUsing(function ($record) {
                                $total = ClassModule::whereHas('mentorshipClass', fn($q) => $q->where('training_id', $record->id))->count();
                                $completed = ClassModule::whereHas('mentorshipClass', fn($q) => $q->where('training_id', $record->id))->where('status', 'completed')->count();
                                return $total > 0 ? "{$completed}/{$total}" : '0';
                            })
                            ->badge()
                            ->color('warning'),
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

//    public static function shouldRegisterNavigation(): bool {
//        if (!auth()->check()) {
//            return false;
//        }
//
//        $userId = auth()->id();
//
//        // Show if user is lead mentor OR accepted co-mentor
//        $isLeadMentor = Training::where('mentor_id', $userId)
//                ->where('type', 'facility_mentorship')
//                ->exists();
//
//        $isCoMentor = MentorshipCoMentor::where('user_id', $userId)
//                ->where('status', 'accepted')
//                ->exists();
//
//        return $isLeadMentor || $isCoMentor;
//    }
}
