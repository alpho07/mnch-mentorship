<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassParticipant;
use App\Models\Training;
use App\Models\FacilityAssessment;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ListMentorshipTrainings extends ListRecords {

    protected static string $resource = MentorshipTrainingResource::class;

    //static protected string|null $breadcrumb = 'Mentorship';

    protected function getHeaderActions(): array {
        return [
                    Actions\CreateAction::make()
                    ->label('New Mentorship')
                    ->icon('heroicon-o-plus')
                    ->color('primary'),
        ];
    }

    public function getTitle(): string {
        return 'Mentorships';
    }

    public function getSubheading(): ?string {
        $stats = $this->getQuickStats();
        return "Facility-based mentorships • {$stats['total']} total • {$stats['active']} active • {$stats['mentees']} mentees";
    }

    // REMOVE the getHeaderWidgets method entirely or fix it like this:
    protected function getHeaderWidgets(): array {
        return [
            \App\Filament\Widgets\MentorshipGuidanceNotice::class,
            \App\Filament\Widgets\MentorshipStatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array {
        return 1;
    }

    public function getTabs(): array {
        if (!auth()->user()->hasRole('super_admin')) {
            return [];
        }

        $trashCount = Training::onlyTrashed()
            ->where('type', 'facility_mentorship')
            ->count();

        return [
            'active' => Tab::make('Active')
                ->icon('heroicon-o-academic-cap'),
            'trash' => Tab::make('Trash')
                ->icon('heroicon-o-trash')
                ->badge($trashCount ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class)
                    ->whereNotNull('trainings.deleted_at')),
        ];
    }

    /**
     * Build a base query scoped by role: admins see all, others see only their own.
     */
    protected function getScopedBaseQuery(): Builder {
        $query = Training::where('type', 'facility_mentorship');

        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where('mentor_id', $user->id);
        }

        return $query;
    }

    protected function getQuickStats(): array {
        return [
            'total' => $this->getScopedBaseQuery()->count(),
            'active' => $this->getScopedBaseQuery()
                    ->where(function (Builder $query) {
                        $query->whereIn('status', ['active', 'ongoing'])
                                ->orWhereHas('mentorshipClasses', fn(Builder $classQuery) => $classQuery->where('status', 'active'));
                    })
                    ->count(),
            'completed' => $this->getScopedBaseQuery()->where('status', 'completed')->count(),
            'draft' => $this->getScopedBaseQuery()->whereIn('status', ['draft', 'new'])->count(),
            'upcoming' => $this->getScopedBaseQuery()->where('start_date', '>', now())->count(),
            'mentees' => $this->getScopedMenteesQuery()->distinct('class_participants.user_id')->count('class_participants.user_id'),
        ];
    }

    protected function getScopedMenteesQuery(): Builder {
        $user = auth()->user();

        return ClassParticipant::query()
                ->whereHas('mentorshipClass.training', function (Builder $query) use ($user) {
                    $query->where('type', 'facility_mentorship');

                    if (!$user->hasRole(['super_admin', 'admin', 'division'])) {
                        $query->where('mentor_id', $user->id);
                    }
                });
    }

    protected function getTabCount(string $tab): int {
        $query = $this->getScopedBaseQuery();

        return match ($tab) {
            'all' => $query->count(),
            'ongoing' => $query->where('status', 'ongoing')->count(),
            'repeat' => $query->where('status', 'repeat')->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'new' => $query->where('status', 'new')->count(),
            default => 0,
        };
    }

    protected function redirectToAssessment(): void {
        $this->redirect(route('filament.admin.resources.facility-assessments.create'));
    }
}
