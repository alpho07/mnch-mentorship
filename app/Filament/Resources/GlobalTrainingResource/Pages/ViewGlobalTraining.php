<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\Training;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\FontWeight;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewGlobalTraining extends ViewRecord {

    protected static string $resource = GlobalTrainingResource::class;


    public function mount(int|string $record): void {
       
     
        $this->record = Training::where('type', 'global_training')
                ->with([
                    'mentor:id,first_name,last_name,cadre_id,facility_id',
                    'mentor.cadre:id,name',
                    'mentor.facility:id,name',
                    'organizer:id,first_name,last_name,department_id',
                    'organizer.department:id,name',
                    'division:id,name',
                    'county:id,name',
                    'partner:id,name',
                    'programs:id,name,description',
                    'modules:id,name,program_id,description',
                    'modules.program:id,name',
                    'methodologies:id,name,description',
                    'locations:id,name,type,address',
                    'assessmentCategories:id,name,description,assessment_method',
                    'trainingMaterials:id,training_id,inventory_item_id,quantity_planned,quantity_used,total_cost,usage_notes',
                    'trainingMaterials.inventoryItem:id,name'
                ])
                ->findOrFail($record); 
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\EditAction::make()
                    ->color('warning'),
                    Actions\Action::make('manage_participants')
                    ->label('Manage Participants')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->url(fn() => static::getResource()::getUrl('participants', ['record' => $this->record])),
                    Actions\Action::make('manage_assessments')
                    ->label('Assessment Matrix')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->url(fn() => static::getResource()::getUrl('assessments', ['record' => $this->record])),
            //->visible(fn() => $this->record->assessmentCategories()->exists()),
            /* Actions\Action::make('smart_insights')
              ->label('Smart Insights')
              ->icon('heroicon-o-sparkles')
              ->color('info')
              ->modalHeading('Training Insights & Recommendations')
              ->modalContent(fn() => view('filament.components.training-insights', [
              'training' => $this->record,
              'insights' => $this->getSmartInsights()
              ]))
              ->modalWidth('5xl'),
              Actions\Action::make('export_participants')
              ->label('Export Participants')
              ->icon('heroicon-o-arrow-down-tray')
              ->color('info')
              ->action(function () {
              return $this->exportTrainingParticipants();
              }),
              /*Actions\Action::make('export_summary')
              ->label('Export Summary')
              ->icon('heroicon-o-arrow-down-tray')
              ->color('gray')
              ->action(function () {
              return $this->exportTrainingSummary();
              }), */
                    Actions\DeleteAction::make()
                    ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Section::make('Training Overview')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('title')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                    TextEntry::make('identifier')
                                    ->label('Training ID')
                                    ->badge()
                                    ->color('primary'),
                                ]),
                                TextEntry::make('description')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                                'draft' => 'gray',
                                                'registration_open' => 'warning',
                                                'ongoing' => 'success',
                                                'completed' => 'primary',
                                                'cancelled' => 'danger',
                                                default => 'gray',
                                            }),
                                    TextEntry::make('type')
                                    ->label('Training Type')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                                'global_training' => 'MOH Training',
                                                'facility_mentorship' => 'Facility Mentorship',
                                                default => ucfirst(str_replace('_', ' ', $state))
                                            })
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('max_participants')
                                    ->label('Max Participants')
                                    ->icon('heroicon-o-users'),
                                ]),
                            ]),
                            Section::make('Training Leadership')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('lead_type')
                                    ->label('Lead Type')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                                'national' => 'primary',
                                                'county' => 'success',
                                                'partner' => 'warning',
                                                default => 'gray',
                                            })
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                                'national' => 'National',
                                                'county' => 'County',
                                                'partner' => 'Partner Led',
                                                default => ucfirst($state),
                                            }),
                                    TextEntry::make('lead_organization')
                                    ->label('Lead Organization')
                                    ->getStateUsing(function ($record): string {
                                        return match ($record->lead_type) {
                                            'national' => $record->division?->name ?? 'Ministry of Health',
                                            'county' => $record->county?->name ?? 'County not specified',
                                            'partner' => $record->partner?->name ?? 'Partner not specified',
                                            default => 'Not specified',
                                        };
                                    })
                                    ->icon(fn($record): string => match ($record->lead_type) {
                                                'national' => 'heroicon-o-building-office-2',
                                                'county' => 'heroicon-o-map',
                                                'partner' => 'heroicon-o-building-office',
                                                default => 'heroicon-o-question-mark-circle',
                                            }),
                                ]),
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('mentor_info')
                                    ->label('Created By')
                                    ->getStateUsing(fn($record) =>
                                            $record->mentor ? "{$record->mentor->first_name} {$record->mentor->last_name}" .
                                            ($record->mentor->cadre ? " ({$record->mentor->cadre->name})" : "") : 'Not assigned'
                                    )
                                    ->icon('heroicon-o-academic-cap')
                                    ->weight(FontWeight::Medium)
                                    ->helperText(fn($record) => $record->mentor?->facility?->name ?? 'No facility'),
                                    TextEntry::make('organizer_info')
                                    ->label('Training Coordinator')
                                    ->getStateUsing(fn($record) =>
                                            $record->organizer ? "{$record->organizer->first_name} {$record->organizer->last_name}" .
                                            ($record->organizer->department ? " ({$record->organizer->department->name})" : "") : 'Not assigned'
                                    )
                                    ->icon('heroicon-o-user')
                                    ->weight(FontWeight::Medium)
                                    ->visible(fn($record) => $record->organizer !== null),
                                ]),
                            ]),
                            Section::make('Schedule & Logistics')
                            ->schema([
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('start_date')
                                    ->date('M j, Y')
                                    ->icon('heroicon-o-calendar'),
                                    TextEntry::make('end_date')
                                    ->date('M j, Y')
                                    ->icon('heroicon-o-calendar'),
                                    TextEntry::make('duration_days')
                                    ->label('Duration')
                                    ->getStateUsing(fn($record) =>
                                            $record->start_date && $record->end_date ? $record->start_date->diffInDays($record->end_date) + 1 . ' days' : 'Not set'
                                    )
                                    ->icon('heroicon-o-clock'),
                                ]),
                                TextEntry::make('locations.name')
                                ->label('Training Locations')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('info')
                                ->getStateUsing(function ($record) {
                                    if ($record->locations && $record->locations->isNotEmpty()) {
                                        return $record->locations->map(function ($location) {
                                                    $address = $location->address ? " - {$location->address}" : "";
                                                    return "{$location->name}{$address}";
                                                })->toArray();
                                    }
                                    return $record->location ? [$record->location] : ['No location specified'];
                                })
                                ->visible(fn($record) => $record->locations->isNotEmpty() || $record->location),
                            ]),
                            Section::make('Content & Programs')
                            ->schema([
                                TextEntry::make('programs.name')
                                ->label('Programs')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('info')
                                ->visible(fn($record) => $record->programs->isNotEmpty()),
                                TextEntry::make('modules.name')
                                ->label('Modules')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('success')
                                ->formatStateUsing(function ($state, $record) {
                                    return $record->modules->map(function ($module) {
                                                return "{$module->program->name} - {$module->name}";
                                            })->toArray();
                                })
                                ->visible(fn($record) => $record->modules->isNotEmpty()),
                                TextEntry::make('methodologies.name')
                                ->label('Methodologies')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('warning')
                                ->visible(fn($record) => $record->methodologies->isNotEmpty()),
                                TextEntry::make('training_approaches')
                                ->label('Training Approaches')
                                ->listWithLineBreaks()
                                ->badge()
                                ->visible(fn($state) => !empty($state)),
                            ]),
                            Section::make('Learning Outcomes & Prerequisites')
                            ->schema([
                                TextEntry::make('learning_outcomes')
                                ->label('Expected Learning Outcomes')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                TextEntry::make('prerequisites')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                            ])
                            ->collapsible()
                            ->visible(fn($record) => !empty($record->learning_outcomes) || !empty($record->prerequisites)),
                            // NEW: Assessment Framework Section
                            Section::make('Assessment Framework')
                            ->schema([
                                RepeatableEntry::make('assessmentCategories')
                                ->label('Assessment Categories')
                                ->schema([
                                    Grid::make(4)
                                    ->schema([
                                        TextEntry::make('name')
                                        ->weight(FontWeight::Medium),
                                        TextEntry::make('pivot.weight_percentage')
                                        ->label('Weight')
                                        ->suffix('%')
                                        ->badge()
                                        ->color('warning'),
                                        TextEntry::make('pivot.pass_threshold')
                                        ->label('Pass Score')
                                        ->suffix('%')
                                        ->badge()
                                        ->color('info'),
                                        TextEntry::make('assessment_method')
                                        ->label('Method')
                                        ->badge()
                                        ->color('success'),
                                    ]),
                                    TextEntry::make('description')
                                    ->prose()
                                    ->columnSpanFull()
                                    ->visible(fn($state) => !empty($state)),
                                ])
                                ->contained(false)
                                ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                                Grid::make(4)
                                ->schema([
                                    TextEntry::make('total_weight')
                                    ->label('Total Weight')
                                    ->getStateUsing(fn($record) =>
                                            $record->assessmentCategories->sum('pivot.weight_percentage') . '%'
                                    )
                                    ->badge()
                                    ->color(function ($record) {
                                        $total = $record->assessmentCategories->sum('pivot.weight_percentage');
                                        return abs($total - 100) < 0.1 ? 'success' : 'danger';
                                    })
                                    ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                                    TextEntry::make('required_categories')
                                    ->label('Required Categories')
                                    ->getStateUsing(fn($record) =>
                                            $record->assessmentCategories->where('pivot.is_required', true)->count()
                                    )
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                                    TextEntry::make('participants_count')
                                    ->label('Total Participants')
                                    ->getStateUsing(fn($record) => $record->participants()->count())
                                    ->badge()
                                    ->color('success'),
                                    TextEntry::make('assessment_completion')
                                    ->label('Assessment Progress')
                                    ->getStateUsing(function ($record) {
                                        $summary = $record->getAssessmentSummary();
                                        return $summary['completion_rate'] . '% Complete';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        $summary = $record->getAssessmentSummary();
                                        $rate = $summary['completion_rate'];
                                        return $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                    })
                                    ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                                ])
                                ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                            ])
                            ->collapsible()
                            ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                            // NEW: Training Materials Section
                            Section::make('Training Materials & Resources')
                            ->schema([
                                RepeatableEntry::make('trainingMaterials')
                                ->label('Materials Used')
                                ->schema([
                                    Grid::make(5)
                                    ->schema([
                                        TextEntry::make('inventoryItem.name')
                                        ->label('Material')
                                        ->weight(FontWeight::Medium),
                                        TextEntry::make('quantity_planned')
                                        ->label('Planned')
                                        ->numeric(),
                                        TextEntry::make('quantity_used')
                                        ->label('Used')
                                        ->numeric()
                                        ->badge()
                                        ->color(fn($record) => match ($this->getMaterialStatus($record)) {
                                                    'As Planned' => 'success',
                                                    'Underutilized' => 'warning',
                                                    'Overused' => 'danger',
                                                    default => 'gray',
                                                }),
                                        TextEntry::make('total_cost')
                                        ->label('Cost')
                                        ->money('KES')
                                        ->badge()
                                        ->color('primary'),
                                        TextEntry::make('usage_percentage')
                                        ->label('Usage %')
                                        ->getStateUsing(fn($record) =>
                                                $record->quantity_planned > 0 ? round(($record->quantity_used / $record->quantity_planned) * 100, 1) . '%' : '0%'
                                        )
                                        ->badge(),
                                    ]),
                                    TextEntry::make('usage_notes')
                                    ->label('Notes')
                                    ->prose()
                                    ->visible(fn($state) => !empty($state))
                                    ->columnSpanFull(),
                                ])
                                ->contained(false)
                                ->visible(fn() => $this->record->trainingMaterials->isNotEmpty()),
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('total_material_cost')
                                    ->label('Total Material Cost')
                                    ->getStateUsing(fn($record) => 'KES ' . number_format($record->trainingMaterials->sum('total_cost'), 2))
                                    ->badge()
                                    ->color('primary'),
                                    TextEntry::make('materials_count')
                                    ->label('Materials Planned')
                                    ->getStateUsing(fn($record) => $record->trainingMaterials->count() . ' items')
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('material_utilization')
                                    ->label('Overall Utilization')
                                    ->getStateUsing(function ($record) {
                                        $materials = $record->trainingMaterials;
                                        if ($materials->isEmpty())
                                            return '0%';

                                        $totalPlanned = $materials->sum('quantity_planned');
                                        $totalUsed = $materials->sum('quantity_used');

                                        return $totalPlanned > 0 ? round(($totalUsed / $totalPlanned) * 100, 1) . '%' : '0%';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        $materials = $record->trainingMaterials;
                                        if ($materials->isEmpty())
                                            return 'gray';

                                        $totalPlanned = $materials->sum('quantity_planned');
                                        $totalUsed = $materials->sum('quantity_used');
                                        $utilization = $totalPlanned > 0 ? ($totalUsed / $totalPlanned) * 100 : 0;

                                        return $utilization >= 80 ? 'success' : ($utilization >= 60 ? 'warning' : 'danger');
                                    }),
                                ])
                                ->visible(fn() => $this->record->trainingMaterials->isNotEmpty()),
                            ])
                            ->collapsible()
                            ->visible(fn() => $this->record->trainingMaterials->isNotEmpty()),
                            Section::make('Participant Statistics')
                            ->schema([
                                Grid::make(4)
                                ->schema([
                                    TextEntry::make('participants_count')
                                    ->label('Total Participants')
                                    ->getStateUsing(fn($record) => $record->participants()->count())
                                    ->badge()
                                    ->color('success'),
                                    TextEntry::make('completion_rate')
                                    ->label('Completion Rate')
                                    ->suffix('%')
                                    ->badge()
                                    ->color(fn($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
                                    TextEntry::make('facilities_represented')
                                    ->label('Facilities Represented')
                                    ->getStateUsing(fn($record) => $record->participants()->with('user.facility')->get()->pluck('user.facility.name')->filter()->unique()->count())
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('average_score')
                                    ->label('Average Score')
                                    ->suffix('%')
                                    ->badge()
                                    ->color('primary'),
                                ]),
                            ]),
                            Section::make('Additional Information')
                            ->schema([
                                TextEntry::make('notes')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                    TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
        ]);
    }

    private function getSmartInsights(): array {
        $insights = [];

        $totalParticipants = $this->record->participants()->count();
        $totalCategories = $this->record->assessmentCategories()->count();
        $totalMaterials = $this->record->trainingMaterials()->count();

        // Participant insights
        if ($totalParticipants == 0) {
            $insights[] = [
                'type' => 'info',
                'title' => 'No Participants Enrolled',
                'message' => 'Add participants to begin the training program.',
                'action' => 'Use the "Manage Participants" button to add participants'
            ];
        }

        // Assessment insights
        if ($totalCategories == 0) {
            $insights[] = [
                'type' => 'info',
                'title' => 'No Assessment Categories',
                'message' => 'Consider configuring assessment categories if participants will be evaluated.',
                'action' => 'Edit the training to add assessment categories'
            ];
        } else {
            // Check weight validation
            $totalWeight = $this->record->assessmentCategories->sum('pivot.weight_percentage');
            if (abs($totalWeight - 100) >= 0.1) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Invalid Assessment Weights',
                    'message' => "Assessment category weights total {$totalWeight}% instead of 100%.",
                    'action' => 'Edit training to adjust category weights'
                ];
            }

            // Assessment progress insight
            if ($totalParticipants > 0) {
                $summary = $this->record->getAssessmentSummary();
                if ($summary['completion_rate'] < 50) {
                    $insights[] = [
                        'type' => 'warning',
                        'title' => 'Low Assessment Progress',
                        'message' => "Only {$summary['completion_rate']}% of assessments completed.",
                        'action' => 'Use Assessment Matrix to evaluate more participants'
                    ];
                }
            }
        }

        // Training content insights
        if ($this->record->programs->isEmpty()) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'No Programs Selected',
                'message' => 'Link this training to programs for better structure.',
                'action' => 'Edit training to add programs and modules'
            ];
        }

        // Materials insights
        if ($totalMaterials > 0) {
            $materials = $this->record->trainingMaterials;
            $totalPlanned = $materials->sum('quantity_planned');
            $totalUsed = $materials->sum('quantity_used');
            $utilization = $totalPlanned > 0 ? ($totalUsed / $totalPlanned) * 100 : 0;

            if ($utilization < 50) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Low Material Utilization',
                    'message' => "Only {$utilization}% of planned materials have been used.",
                    'action' => 'Review material usage and update quantities'
                ];
            } elseif ($utilization > 120) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Material Overuse',
                    'message' => "Material usage is {$utilization}% of planned quantities.",
                    'action' => 'Review and adjust material planning for future trainings'
                ];
            }
        }

        // Location insights
        if ($this->record->locations->isEmpty() && empty($this->record->location)) {
            $insights[] = [
                'type' => 'info',
                'title' => 'No Location Specified',
                'message' => 'Add training location information for better coordination.',
                'action' => 'Edit training to add location details'
            ];
        }

        return $insights;
    }

    private function getMaterialStatus($material): string {
        if ($material->quantity_planned <= 0)
            return 'Unknown';

        $usagePercent = ($material->quantity_used / $material->quantity_planned) * 100;

        if ($usagePercent == 0)
            return 'Not Used';
        if ($usagePercent < 50)
            return 'Underutilized';
        if ($usagePercent <= 110)
            return 'As Planned';
        return 'Overused';
    }

    /**
     * Export participants for this specific training
     */
    protected function exportTrainingParticipants(): StreamedResponse {
        $participants = $this->record->participants()
                ->with([
                    'user.facility.subcounty.county.division',
                    'user.department',
                    'user.cadre',
                    'training.programs',
                    'training.modules.program',
                    'training.methodologies',
                    'training.mentor',
                    'training.county',
                    'training.partner',
                    'assessmentResults.assessmentCategory',
                    'outcome'
                ])
                ->get();

        $filename = "training_participants_{$this->record->identifier}_" . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($participants) {
                    $file = fopen('php://output', 'w');

                    // CSV Headers
                    $headers = [
                        'Participant Name',
                        'Phone Number',
                        'Email',
                        'ID Number',
                        'Facility Name',
                        'Facility MFL Code',
                        'Department',
                        'Cadre',
                        'Subcounty',
                        'County',
                        'Division',
                        'Registration Date',
                        'Attendance Status',
                        'Completion Status',
                        'Completion Date',
                        'Certificate Issued',
                        'Overall Assessment Score (%)',
                        'Overall Assessment Status',
                        'Assessment Categories Completed',
                        'Total Assessment Categories',
                        'Assessment Progress (%)',
                        'Individual Category Results',
                        'Final Outcome',
                        'Participant Notes'
                    ];

                    fputcsv($file, $headers);

                    $totalCategories = $this->record->assessmentCategories()->count();

                    foreach ($participants as $participant) {
                        $user = $participant->user;
                        $facility = $user->facility;
                        $assessmentResults = $participant->assessmentResults;
                        $assessedCategories = $assessmentResults->count();

                        // Calculate overall assessment score and status
                        $overallCalculation = $this->record->calculateOverallScore($participant);

                        // Individual category results
                        $categoryResults = $assessmentResults->map(function ($result) {
                                    return "{$result->assessmentCategory->name}: {$result->result}";
                                })->implode('; ');

                        $row = [
                            $user->full_name,
                            $user->phone,
                            $user->email,
                            $user->id_number,
                            $facility?->name,
                            $facility?->mfl_code,
                            $user->department?->name,
                            $user->cadre?->name,
                            $facility?->subcounty?->name,
                            $facility?->subcounty?->county?->name,
                            $facility?->subcounty?->county?->division?->name,
                            $participant->registration_date?->format('Y-m-d H:i'),
                            ucfirst($participant->attendance_status),
                            ucfirst($participant->completion_status),
                            $participant->completion_date?->format('Y-m-d'),
                            $participant->certificate_issued ? 'Yes' : 'No',
                            $totalCategories > 0 ? number_format($overallCalculation['score'], 1) : 'N/A',
                            $totalCategories > 0 ? $overallCalculation['status'] : 'No Assessments',
                            $assessedCategories,
                            $totalCategories,
                            $totalCategories > 0 ? number_format(($assessedCategories / $totalCategories) * 100, 1) : '0',
                            $categoryResults,
                            $participant->outcome?->name,
                            $participant->notes
                        ];

                        fputcsv($file, $row);
                    }

                    fclose($file);
                }, $filename, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function exportTrainingSummary() {
        $filename = "global_training_summary_{$this->record->identifier}_" . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () {
                    $file = fopen('php://output', 'w');

                    // Training Overview
                    fputcsv($file, ['MOH TRAINING SUMMARY']);
                    fputcsv($file, ['Generated on: ' . now()->format('Y-m-d H:i:s')]);
                    fputcsv($file, ['']);

                    // Basic Information
                    fputcsv($file, ['BASIC INFORMATION']);
                    fputcsv($file, ['Training Title', $this->record->title]);
                    fputcsv($file, ['Training ID', $this->record->identifier]);
                    fputcsv($file, ['Training Type', 'MOH Training']);
                    fputcsv($file, ['Status', ucfirst($this->record->status)]);
                    fputcsv($file, ['Lead Type', ucfirst($this->record->lead_type)]);
                    fputcsv($file, ['Lead Organization', $this->record->lead_organization]);
                    fputcsv($file, ['Start Date', $this->record->start_date?->format('Y-m-d')]);
                    fputcsv($file, ['End Date', $this->record->end_date?->format('Y-m-d')]);
                    fputcsv($file, ['Created By', $this->record->mentor ? "{$this->record->mentor->first_name} {$this->record->mentor->last_name}" : 'Not assigned']);
                    fputcsv($file, ['Location(s)', $this->record->locations->pluck('name')->implode(', ') ?: $this->record->location ?: 'Not specified']);
                    fputcsv($file, ['']);

                    // Training Content Summary
                    if ($this->record->programs->isNotEmpty()) {
                        fputcsv($file, ['TRAINING PROGRAMS']);
                        fputcsv($file, ['Program Name', 'Description']);
                        foreach ($this->record->programs as $program) {
                            fputcsv($file, [$program->name, $program->description ?? 'N/A']);
                        }
                        fputcsv($file, ['']);
                    }

                    if ($this->record->modules->isNotEmpty()) {
                        fputcsv($file, ['TRAINING MODULES']);
                        fputcsv($file, ['Module Name', 'Program', 'Description']);
                        foreach ($this->record->modules as $module) {
                            fputcsv($file, [
                                $module->name,
                                $module->program->name ?? 'N/A',
                                $module->description ?? 'N/A'
                            ]);
                        }
                        fputcsv($file, ['']);
                    }

                    if ($this->record->methodologies->isNotEmpty()) {
                        fputcsv($file, ['TRAINING METHODOLOGIES']);
                        fputcsv($file, ['Methodology', 'Description']);
                        foreach ($this->record->methodologies as $methodology) {
                            fputcsv($file, [
                                $methodology->name,
                                $methodology->description ?? 'N/A'
                            ]);
                        }
                        fputcsv($file, ['']);
                    }

                    // Participant Statistics
                    fputcsv($file, ['PARTICIPANT STATISTICS']);
                    fputcsv($file, ['Total Participants', $this->record->participants()->count()]);
                    fputcsv($file, ['Completion Rate (%)', $this->record->completion_rate]);
                    fputcsv($file, ['Average Score (%)', number_format($this->record->average_score, 1)]);
                    fputcsv($file, ['Facilities Represented', $this->record->participants()->with('user.facility')->get()->pluck('user.facility.name')->filter()->unique()->count()]);
                    fputcsv($file, ['']);

                    // Assessment Categories (if any)
                    if ($this->record->assessmentCategories->isNotEmpty()) {
                        fputcsv($file, ['ASSESSMENT CATEGORIES']);
                        fputcsv($file, ['Category', 'Weight (%)', 'Pass Threshold (%)', 'Method', 'Required']);
                        foreach ($this->record->assessmentCategories as $category) {
                            fputcsv($file, [
                                $category->name,
                                $category->pivot->weight_percentage,
                                $category->pivot->pass_threshold,
                                $category->assessment_method,
                                $category->pivot->is_required ? 'Yes' : 'No'
                            ]);
                        }

                        // Assessment Summary
                        $summary = $this->record->getAssessmentSummary();
                        fputcsv($file, ['']);
                        fputcsv($file, ['ASSESSMENT SUMMARY']);
                        fputcsv($file, ['Total Participants', $summary['total_mentees']]);
                        fputcsv($file, ['Passed Participants', $summary['passed_mentees']]);
                        fputcsv($file, ['Failed Participants', $summary['failed_mentees']]);
                        fputcsv($file, ['Incomplete Assessments', $summary['incomplete_mentees']]);
                        fputcsv($file, ['Assessment Completion Rate (%)', $summary['completion_rate']]);
                        fputcsv($file, ['Pass Rate (%)', $summary['pass_rate']]);
                        fputcsv($file, ['Average Assessment Score (%)', $summary['average_score']]);
                        fputcsv($file, ['']);
                    }

                    // Materials Summary (if any)
                    if ($this->record->trainingMaterials->isNotEmpty()) {
                        fputcsv($file, ['MATERIALS SUMMARY']);
                        fputcsv($file, ['Material', 'Planned Qty', 'Used Qty', 'Cost (KES)', 'Utilization (%)']);
                        foreach ($this->record->trainingMaterials as $material) {
                            $utilization = $material->quantity_planned > 0 ? round(($material->quantity_used / $material->quantity_planned) * 100, 1) : 0;
                            fputcsv($file, [
                                $material->inventoryItem->name,
                                $material->quantity_planned,
                                $material->quantity_used ?? 0,
                                number_format($material->total_cost, 2),
                                $utilization
                            ]);
                        }
                        fputcsv($file, ['']);
                        fputcsv($file, ['MATERIAL COSTS SUMMARY']);
                        fputcsv($file, ['Total Planned Cost', 'KES ' . number_format($this->record->trainingMaterials->sum('total_cost'), 2)]);
                        fputcsv($file, ['Total Items', $this->record->trainingMaterials->count()]);
                    }

                    fclose($file);
                }, $filename, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
