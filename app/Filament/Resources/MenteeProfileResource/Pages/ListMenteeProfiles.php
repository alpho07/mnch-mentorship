<?php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
use App\Models\User;
use App\Models\MenteeStatusLog;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListMenteeProfiles extends ListRecords
{
    protected static string $resource = MenteeProfileResource::class;

    public function getTitle(): string
    {
        return 'Mentee Profiles & Analytics';
    }

    public function getSubheading(): ?string
    {
        $stats = $this->getOverviewStats();
        return "Comprehensive mentee tracking • {$stats['total']} mentees • {$stats['active']} active • {$stats['at_risk']} at risk";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analytics_dashboard')
                ->label('Analytics Dashboard')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('Mentee Analytics Dashboard')
                ->modalContent(fn () => view('filament.components.mentee-analytics', [
                    'stats' => $this->getDetailedAnalytics()
                ]))
                ->modalWidth('7xl'),

            Actions\Action::make('export_profiles')
                ->label('Export Profiles')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return $this->exportMenteeProfiles();
                }),

            Actions\Action::make('attrition_report')
                ->label('Attrition Report')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->modalHeading('Attrition Analysis')
                ->modalContent(fn () => view('filament.components.attrition-analysis', [
                    'data' => $this->getAttritionAnalysis()
                ]))
                ->modalWidth('6xl'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            $this->getMenteeStatsWidget(),
        ];
    }

    protected function getMenteeStatsWidget()
    {
        $stats = $this->getOverviewStats();
        
        return new class($stats) extends \Filament\Widgets\Widget {
            protected static string $view = 'filament.widgets.mentee-overview-stats';
            
            public array $stats;
            
            public function __construct(array $stats)
            {
                $this->stats = $stats;
            }
        };
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Mentees')
                ->badge($this->getTabCount('all'))
                ->badgeColor('gray'),

            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->activeMentees())
                ->badge($this->getTabCount('active'))
                ->badgeColor('success'),

            'high_performers' => Tab::make('High Performers')
                ->modifyQueryUsing(fn (Builder $query) => $query->highPerformers())
                ->badge($this->getTabCount('high_performers'))
                ->badgeColor('primary'),

            'at_risk' => Tab::make('At Risk')
                ->modifyQueryUsing(fn (Builder $query) => $query->atRisk())
                ->badge($this->getTabCount('at_risk'))
                ->badgeColor('danger'),

            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('statusLogs', function ($q) {
                        $q->whereIn('new_status', MenteeStatusLog::getAttritionStatuses())
                          ->latest('effective_date')
                          ->limit(1);
                    })
                )
                ->badge($this->getTabCount('inactive'))
                ->badgeColor('secondary'),

            'recent_changes' => Tab::make('Recent Changes')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('statusLogs', function ($q) {
                        $q->where('created_at', '>=', now()->subDays(30));
                    })
                )
                ->badge($this->getTabCount('recent_changes'))
                ->badgeColor('info'),
        ];
    }

    private function getOverviewStats(): array
    {
        $totalMentees = static::getEloquentQuery()->count();
        
        $activeMentees = static::getEloquentQuery()
            ->activeMentees()
            ->count();

        $atRiskMentees = static::getEloquentQuery()
            ->atRisk()
            ->count();

        $highPerformers = static::getEloquentQuery()
            ->highPerformers()
            ->count();

        $recentAttrition = User::whereHas('statusLogs', function ($query) {
            $query->whereIn('new_status', MenteeStatusLog::getAttritionStatuses())
                  ->where('effective_date', '>=', now()->subDays(30));
        })->count();

        return [
            'total' => $totalMentees,
            'active' => $activeMentees,
            'at_risk' => $atRiskMentees,
            'high_performers' => $highPerformers,
            'recent_attrition' => $recentAttrition,
            'retention_rate' => $totalMentees > 0 ? round((($totalMentees - $recentAttrition) / $totalMentees) * 100, 1) : 0,
        ];
    }

    private function getTabCount(string $tab): int
    {
        return match ($tab) {
            'all' => static::getEloquentQuery()->count(),
            'active' => static::getEloquentQuery()->activeMentees()->count(),
            'high_performers' => static::getEloquentQuery()->highPerformers()->count(),
            'at_risk' => static::getEloquentQuery()->atRisk()->count(),
            'inactive' => User::whereHas('statusLogs', function ($q) {
                $q->whereIn('new_status', MenteeStatusLog::getAttritionStatuses())
                  ->latest('effective_date')
                  ->limit(1);
            })->count(),
            'recent_changes' => static::getEloquentQuery()
                ->whereHas('statusLogs', function ($q) {
                    $q->where('created_at', '>=', now()->subDays(30));
                })
                ->count(),
            default => 0,
        };
    }

    private function getDetailedAnalytics(): array
    {
        // Performance distribution
        $performanceDistribution = static::getEloquentQuery()
            ->get()
            ->groupBy(function ($mentee) {
                $score = $mentee->overall_training_score;
                if ($score >= 90) return 'Excellent (90%+)';
                if ($score >= 80) return 'Very Good (80-89%)';
                if ($score >= 70) return 'Good (70-79%)';
                if ($score >= 60) return 'Fair (60-69%)';
                return 'Needs Improvement (<60%)';
            })
            ->map->count();

        // Department-wise performance
        $departmentPerformance = static::getEloquentQuery()
            ->with('department')
            ->get()
            ->groupBy('department.name')
            ->map(function ($mentees) {
                return [
                    'count' => $mentees->count(),
                    'avg_score' => $mentees->avg('overall_training_score'),
                    'completion_rate' => $mentees->avg('training_completion_rate'),
                ];
            });

        // Trend analysis
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyTrends[$month->format('M Y')] = [
                'new_mentees' => static::getEloquentQuery()
                    ->whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)
                    ->count(),
                'attrition' => User::whereHas('statusLogs', function ($q) use ($month) {
                    $q->whereIn('new_status', MenteeStatusLog::getAttritionStatuses())
                      ->whereMonth('effective_date', $month->month)
                      ->whereYear('effective_date', $month->year);
                })->count(),
            ];
        }

        return [
            'performance_distribution' => $performanceDistribution,
            'department_performance' => $departmentPerformance,
            'monthly_trends' => $monthlyTrends,
        ];
    }

    private function getAttritionAnalysis(): array
    {
        $attritionReasons = MenteeStatusLog::whereIn('new_status', MenteeStatusLog::getAttritionStatuses())
            ->where('effective_date', '>=', now()->subYear())
            ->get()
            ->groupBy('new_status')
            ->map->count();

        $monthlyAttrition = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyAttrition[$month->format('M Y')] = MenteeStatusLog::whereIn('new_status', MenteeStatusLog::getAttritionStatuses())
                ->whereMonth('effective_date', $month->month)
                ->whereYear('effective_date', $month->year)
                ->count();
        }

        // Attrition by department
        $departmentAttrition = MenteeStatusLog::whereIn('new_status', MenteeStatusLog::getAttritionStatuses())
            ->where('effective_date', '>=', now()->subYear())
            ->with('user.department')
            ->get()
            ->groupBy('user.department.name')
            ->map->count();

        return [
            'reasons' => $attritionReasons,
            'monthly_trends' => $monthlyAttrition,
            'department_breakdown' => $departmentAttrition,
            'total_attrition_12m' => array_sum($monthlyAttrition),
        ];
    }

    private function exportMenteeProfiles()
    {
        $filename = 'mentee_profiles_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');

            // CSV Headers
            $headers = [
                'Mentee Name',
                'Phone',
                'Email',
                'Facility',
                'Department',
                'Cadre',
                'Current Status',
                'Status Since',
                'Total Trainings',
                'Completed Trainings',
                'Overall Score (%)',
                'Completion Rate (%)',
                'Performance Trend',
                'Attrition Risk',
                'Last Training',
                'Registration Date',
            ];

            fputcsv($file, $headers);

            // Get all mentee data
            $mentees = static::getEloquentQuery()
                ->with([
                    'facility',
                    'department',
                    'cadre',
                    'statusLogs' => fn($q) => $q->latest('effective_date')->limit(1),
                    'trainingParticipations.training'
                ])
                ->get();

            foreach ($mentees as $mentee) {
                $latestStatus = $mentee->statusLogs->first();
                $summary = $mentee->training_history_summary;

                $row = [
                    $mentee->full_name,
                    $mentee->phone,
                    $mentee->email,
                    $mentee->facility?->name,
                    $mentee->department?->name,
                    $mentee->cadre?->name,
                    $latestStatus ? ucwords(str_replace('_', ' ', $latestStatus->new_status)) : 'Active',
                    $latestStatus?->effective_date?->format('Y-m-d'),
                    $summary['total_trainings'],
                    $summary['completed'],
                    $mentee->overall_training_score ? number_format($mentee->overall_training_score, 1) : 'N/A',
                    $mentee->training_completion_rate,
                    $mentee->performance_trend,
                    $mentee->attrition_risk,
                    $summary['latest_training'],
                    $mentee->created_at?->format('Y-m-d'),
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}