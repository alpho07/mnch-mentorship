<?php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
use App\Models\MenteeStatusLog;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Filament\Notifications\Notification;

class ViewMenteeProfile extends ViewRecord
{
    protected static string $resource = MenteeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_status')
                ->label('Update Status')
                ->icon('heroicon-o-pencil')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('new_status')
                        ->label('New Status')
                        ->options(MenteeStatusLog::getStatusOptions())
                        ->required()
                        ->default($this->record->current_status),

                    Forms\Components\DatePicker::make('effective_date')
                        ->label('Effective Date')
                        ->default(now())
                        ->required()
                        ->maxDate(now()),

                    Forms\Components\TextInput::make('reason')
                        ->label('Reason for Change')
                        ->required()
                        ->placeholder('e.g., Voluntary resignation, Transfer to another facility'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Additional Notes')
                        ->rows(3)
                        ->placeholder('Any additional context or details...'),
                ])
                ->action(function (array $data): void {
                    $this->record->updateStatus(
                        $data['new_status'],
                        $data['reason'],
                        $data['notes'] ?? null,
                        $data['effective_date']
                    );

                    Notification::make()
                        ->title('Status Updated')
                        ->body("Status updated to " . ucwords(str_replace('_', ' ', $data['new_status'])))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('performance_insights')
                ->label('Performance Insights')
                ->icon('heroicon-o-light-bulb')
                ->color('info')
                ->modalHeading(fn () => "Performance Insights - {$this->record->full_name}")
                ->modalContent(fn () => view('filament.components.mentee-performance-insights', [
                    'mentee' => $this->record,
                    'insights' => $this->getPerformanceInsights()
                ]))
                ->modalWidth('5xl'),

            Actions\Action::make('training_roadmap')
                ->label('Training Roadmap')
                ->icon('heroicon-o-map')
                ->color('success')
                ->modalHeading('Recommended Training Path')
                ->modalContent(fn () => view('filament.components.training-roadmap', [
                    'mentee' => $this->record,
                    'recommendations' => $this->getTrainingRecommendations()
                ]))
                ->modalWidth('4xl'),

            Actions\Action::make('export_profile')
                ->label('Export Profile')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    return $this->exportMenteeProfile();
                }),

            Actions\Action::make('intervention_plan')
                ->label('Create Intervention Plan')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn () => $this->record->attrition_risk === 'High')
                ->form([
                    Forms\Components\Section::make('Intervention Strategy')
                        ->description('Create a plan to address performance or retention issues')
                        ->schema([
                            Forms\Components\Select::make('intervention_type')
                                ->label('Intervention Type')
                                ->options([
                                    'additional_mentoring' => 'Additional Mentoring Sessions',
                                    'skill_development' => 'Targeted Skill Development',
                                    'career_counseling' => 'Career Counseling',
                                    'workload_adjustment' => 'Workload Adjustment',
                                    'department_transfer' => 'Department Transfer',
                                    'remedial_training' => 'Remedial Training',
                                ])
                                ->required()
                                ->multiple(),

                            Forms\Components\Textarea::make('intervention_plan')
                                ->label('Detailed Plan')
                                ->rows(4)
                                ->required()
                                ->placeholder('Describe the specific interventions and timeline...'),

                            Forms\Components\DatePicker::make('review_date')
                                ->label('Review Date')
                                ->required()
                                ->minDate(now()->addWeek()),

                            Forms\Components\Select::make('assigned_to')
                                ->label('Assigned To')
                                ->relationship('facility.users', 'first_name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                ->searchable()
                                ->required(),
                        ])
                ])
                ->action(function (array $data) {
                    // Store intervention plan (you would create an InterventionPlan model)
                    Notification::make()
                        ->title('Intervention Plan Created')
                        ->body('Intervention plan has been created and assigned.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function getPerformanceInsights(): array
    {
        $insights = [];
        $mentee = $this->record;

        // Performance analysis
        $overallScore = $mentee->overall_training_score;
        $completionRate = $mentee->training_completion_rate;
        $trend = $mentee->performance_trend;

        // Strength areas
        $strongCategories = [];
        $weakCategories = [];

        // Get assessment results across all trainings
        $assessmentResults = $mentee->assessmentResults()
            ->with('assessmentCategory')
            ->get()
            ->groupBy('assessmentCategory.name');

        foreach ($assessmentResults as $categoryName => $results) {
            $avgScore = $results->avg('score');
            if ($avgScore >= 85) {
                $strongCategories[] = $categoryName . ' (' . number_format($avgScore, 1) . '%)';
            } elseif ($avgScore < 70) {
                $weakCategories[] = $categoryName . ' (' . number_format($avgScore, 1) . '%)';
            }
        }

        // Generate insights
        if ($overallScore >= 85) {
            $insights[] = [
                'type' => 'success',
                'title' => 'High Performer',
                'message' => "Excellent overall performance ({$overallScore}%). Consider for advanced programs or peer mentoring roles.",
            ];
        } elseif ($overallScore < 60) {
            $insights[] = [
                'type' => 'danger',
                'title' => 'Performance Concern',
                'message' => "Below-average performance ({$overallScore}%). Immediate intervention recommended.",
            ];
        }

        if ($trend === 'Improving') {
            $insights[] = [
                'type' => 'success',
                'title' => 'Positive Trajectory',
                'message' => 'Performance is improving over time. Current mentorship approach is effective.',
            ];
        } elseif ($trend === 'Declining') {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Declining Performance',
                'message' => 'Performance has been declining. Review mentorship strategy and identify support needs.',
            ];
        }

        if ($completionRate < 70) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Low Completion Rate',
                'message' => "Only {$completionRate}% completion rate. Investigate barriers to training completion.",
            ];
        }

        if (!empty($strongCategories)) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Strength Areas',
                'message' => 'Strong performance in: ' . implode(', ', $strongCategories),
            ];
        }

        if (!empty($weakCategories)) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Development Areas',
                'message' => 'Needs improvement in: ' . implode(', ', $weakCategories),
            ];
        }

        // Attendance insights
        $recentTrainings = $mentee->trainingParticipations()
            ->where('created_at', '>=', now()->subMonths(6))
            ->count();

        if ($recentTrainings == 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Training Inactivity',
                'message' => 'No training participation in the last 6 months. Consider enrolling in relevant programs.',
            ];
        }

        return $insights;
    }

    private function getTrainingRecommendations(): array
    {
        $mentee = $this->record;
        $recommendations = [];

        // Based on weak assessment categories
        $assessmentResults = $mentee->assessmentResults()
            ->with('assessmentCategory')
            ->get()
            ->groupBy('assessmentCategory.name');

        foreach ($assessmentResults as $categoryName => $results) {
            $avgScore = $results->avg('score');
            if ($avgScore < 70) {
                $recommendations[] = [
                    'type' => 'remedial',
                    'title' => "Remedial Training: {$categoryName}",
                    'description' => "Focus on improving {$categoryName} skills (current average: " . number_format($avgScore, 1) . "%)",
                    'priority' => 'high',
                    'estimated_duration' => '2-3 weeks',
                ];
            }
        }

        // Career progression recommendations
        if ($mentee->overall_training_score >= 85) {
            $recommendations[] = [
                'type' => 'advancement',
                'title' => 'Leadership Development Program',
                'description' => 'High performer ready for leadership training and mentor certification',
                'priority' => 'medium',
                'estimated_duration' => '4-6 weeks',
            ];
        }

        // Department-specific recommendations
        if ($mentee->department) {
            $recommendations[] = [
                'type' => 'specialization',
                'title' => "Advanced {$mentee->department->name} Training",
                'description' => "Specialized skills development for {$mentee->department->name} department",
                'priority' => 'medium',
                'estimated_duration' => '3-4 weeks',
            ];
        }

        // If no specific recommendations, provide general ones
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'general',
                'title' => 'Continuing Education',
                'description' => 'Regular skill updates and refresher courses to maintain competency',
                'priority' => 'low',
                'estimated_duration' => '1-2 weeks',
            ];
        }

        return $recommendations;
    }

    private function exportMenteeProfile()
    {
        $mentee = $this->record;
        $filename = "mentee_profile_{$mentee->id}_" . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($mentee) {
            $file = fopen('php://output', 'w');

            // Profile Header
            fputcsv($file, ['MENTEE PROFILE REPORT']);
            fputcsv($file, ['Generated on: ' . now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['']);

            // Basic Information
            fputcsv($file, ['PERSONAL INFORMATION']);
            fputcsv($file, ['Full Name', $mentee->full_name]);
            fputcsv($file, ['Phone', $mentee->phone]);
            fputcsv($file, ['Email', $mentee->email]);
            fputcsv($file, ['ID Number', $mentee->id_number]);
            fputcsv($file, ['Facility', $mentee->facility?->name]);
            fputcsv($file, ['Department', $mentee->department?->name]);
            fputcsv($file, ['Cadre', $mentee->cadre?->name]);
            fputcsv($file, ['Current Status', ucwords(str_replace('_', ' ', $mentee->current_status))]);
            fputcsv($file, ['Registration Date', $mentee->created_at?->format('Y-m-d')]);
            fputcsv($file, ['']);

            // Performance Summary
            $summary = $mentee->training_history_summary;
            fputcsv($file, ['PERFORMANCE SUMMARY']);
            fputcsv($file, ['Total Trainings', $summary['total_trainings']]);
            fputcsv($file, ['Completed Trainings', $summary['completed']]);
            fputcsv($file, ['Passed Trainings', $summary['passed']]);
            fputcsv($file, ['Overall Score', $mentee->overall_training_score ? number_format($mentee->overall_training_score, 1) . '%' : 'N/A']);
            fputcsv($file, ['Completion Rate', $mentee->training_completion_rate . '%']);
            fputcsv($file, ['Performance Trend', $mentee->performance_trend]);
            fputcsv($file, ['Attrition Risk', $mentee->attrition_risk]);
            fputcsv($file, ['']);

            // Training History
            fputcsv($file, ['TRAINING HISTORY']);
            fputcsv($file, ['Training Title', 'Start Date', 'Completion Status', 'Overall Score']);
            
            foreach ($mentee->trainingParticipations as $participation) {
                $scores = $participation->objectiveResults->pluck('score');
                $avgScore = $scores->isEmpty() ? 'Not assessed' : number_format($scores->avg(), 1) . '%';
                
                fputcsv($file, [
                    $participation->training->title,
                    $participation->training->start_date?->format('Y-m-d'),
                    ucfirst($participation->completion_status),
                    $avgScore
                ]);
            }
            fputcsv($file, ['']);

            // Status History
            fputcsv($file, ['STATUS HISTORY']);
            fputcsv($file, ['Status', 'Effective Date', 'Reason', 'Changed By']);
            
            foreach ($mentee->statusLogs as $log) {
                fputcsv($file, [
                    ucwords(str_replace('_', ' ', $log->new_status)),
                    $log->effective_date?->format('Y-m-d'),
                    $log->reason,
                    $log->changedBy?->full_name ?? 'System'
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}