<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\FacilityAssessment;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMentorshipTrainings extends ListRecords
{
    protected static string $resource = MentorshipTrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Mentorship Program')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->before(function () {
                    // Check if user's facility has valid assessment
                    $userFacility = auth()->user()->facility_id;
                    if ($userFacility) {
                        $assessment = FacilityAssessment::where('facility_id', $userFacility)
                            ->valid()
                            ->latest()
                            ->first();
                            
                        if (!$assessment) {
                            $this->redirectToAssessment();
                            return false;
                        }
                    }
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Mentorship Training Programs';
    }

    public function getSubheading(): ?string
    {
        $stats = $this->getQuickStats();
        return "Facility-based mentorship programs • {$stats['total']} total • {$stats['ongoing']} active • {$stats['mentees']} mentees";
    }

    // REMOVE the getHeaderWidgets method entirely or fix it like this:
    protected function getHeaderWidgets(): array
    {
        return [
            // Return the widget class name, not an instance
            \App\Filament\Widgets\MentorshipStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Programs')
                ->badge($this->getTabCount('all'))
                ->badgeColor('gray'),

            'ongoing' => Tab::make('Ongoing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'ongoing'))
                ->badge($this->getTabCount('ongoing'))
                ->badgeColor('success'),

            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('start_date', '>', now()))
                ->badge($this->getTabCount('upcoming'))
                ->badgeColor('info'),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge($this->getTabCount('completed'))
                ->badgeColor('primary'),

            'draft' => Tab::make('Drafts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge($this->getTabCount('draft'))
                ->badgeColor('secondary'),
        ];
    }

    protected function getQuickStats(): array
    {
        $query = Training::where('type', 'facility_mentorship');

        return [
            'total' => $query->count(),
            'ongoing' => $query->where('status', 'ongoing')->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'draft' => $query->where('status', 'draft')->count(),
            'upcoming' => $query->where('start_date', '>', now())->count(),
            'mentees' => $query->withCount('participants')->get()->sum('participants_count'),
        ];
    }

    protected function getTabCount(string $tab): int
    {
        $query = Training::where('type', 'facility_mentorship');

        return match ($tab) {
            'all' => $query->count(),
            'ongoing' => $query->where('status', 'ongoing')->count(),
            'upcoming' => $query->where('start_date', '>', now())->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'draft' => $query->where('status', 'draft')->count(),
            default => 0,
        };
    }

    protected function redirectToAssessment(): void
    {
        $this->redirect(route('filament.admin.resources.facility-assessments.create'));
    }
}