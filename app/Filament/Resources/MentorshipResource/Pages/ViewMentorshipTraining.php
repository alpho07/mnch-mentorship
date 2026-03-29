<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MenteeAssessmentResult;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class ViewMentorshipTraining extends ViewRecord {

    protected static string $resource = MentorshipTrainingResource::class;

    public function mount(int|string $record): void {
        $this->record = Training::where('type', 'facility_mentorship')
                ->with([
                    'facility:id,name',
                    'mentor:id,first_name,last_name,cadre_id',
                    'mentor.cadre:id,name',
                    'organizer:id,first_name,last_name,department_id',
                    'organizer.department:id,name',
                    'programs:id,name,description',
                    'modules:id,name,program_id,description',
                    'modules.program:id,name',
                    'methodologies:id,name,description',
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
                    Actions\Action::make('manage_classes')
                    ->label('Manage Classes')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->url(fn() => static::getResource()::getUrl('mentees', ['record' => $this->record])),
//                    Actions\Action::make('assessment_matrix')
//                    ->label('Assessment Matrix')
//                    ->icon('heroicon-o-clipboard-document-check')
//                    ->color('primary')
//                    ->url(fn() => static::getResource()::getUrl('assessments', ['record' => $this->record])),
            /* Actions\Action::make('smart_insights')
              ->label('Smart Insights')
              ->icon('heroicon-o-sparkles')
              ->color('info')
              ->modalHeading('Training Insights & Recommendations')
              ->modalContent(fn () => view('filament.components.training-insights', [
              'training' => $this->record,
              'insights' => $this->getSmartInsights()
              ]))
              ->modalWidth('5xl'),

              Actions\Action::make('export_summary')
              ->label('Export Summary')
              ->icon('heroicon-o-arrow-down-tray')
              ->color('gray')
              ->action(function () {
              return $this->exportTrainingSummary();
              }), */
//                    Actions\DeleteAction::make()
//                    ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Section::make('Mentorship Overview')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('title')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                    TextEntry::make('identifier')
                                    ->label('Mentorship ID')
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
                                                'new' => 'New',
                                                'ongoing' => 'success',
                                                'completed' => 'primary',
                                                'cancelled' => 'danger',
                                                default => 'new',
                                            }),
                                    TextEntry::make('facility.name')
                                    ->label('Facility')
                                    ->icon('heroicon-o-building-office-2')
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('max_participants')
                                    ->label('Max Mentees')
                                    ->icon('heroicon-o-users'),
                                ]),
                            ]),
                            Section::make('Schedule & Team')
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
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('mentor_info')
                                    ->label('Lead Mentor')
                                    ->getStateUsing(fn($record) =>
                                            $record->mentor ? "{$record->mentor->first_name} {$record->mentor->last_name}" .
                                            ($record->mentor->cadre ? " ({$record->mentor->cadre->name})" : "") : 'Not assigned'
                                    )
                                    ->icon('heroicon-o-academic-cap')
                                    ->weight(FontWeight::Medium),
                                    TextEntry::make('lead_organization')
                                    ->label('Lead Organization')
                                    ->getStateUsing(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => $record->division?->name ?? 'Ministry of Health',
                                            'county' => $record->county?->name ?? 'County not specified',
                                            'partner' => $record->partner?->name ?? 'Partner not specified',
                                            default => 'Not specified'
                                        };
                                    })
                                    ->icon(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => 'heroicon-o-building-office-2',
                                            'county' => 'heroicon-o-map-pin',
                                            'partner' => 'heroicon-o-users',
                                            default => 'heroicon-o-question-mark-circle'
                                        };
                                    })
                                    ->badge(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => 'National',
                                            'county' => 'County',
                                            'partner' => 'Partner',
                                            default => 'Unknown'
                                        };
                                    })
                                    ->color(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => 'primary',
                                            'county' => 'success',
                                            'partner' => 'warning',
                                            default => 'gray'
                                        };
                                    })
                                    ->weight(FontWeight::Medium),
                                ]),
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
                                ->label('Approaches')
                                ->listWithLineBreaks()
                                ->badge()
                                ->visible(fn($state) => !empty($state)),
                            ]),
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
                                Section::make('Mentee Statistics')
                                ->schema([
                                    Grid::make(4)
                                    ->schema([
                                        TextEntry::make('participants_count')
                                        ->label('Total Mentees')
                                        ->getStateUsing(fn($record) => $record->participants()->count())
                                        ->badge()
                                        ->color('success'),
                                        TextEntry::make('completion_rate')
                                        ->label('Completion Rate')
                                        ->suffix('%')
                                        ->badge()
                                        ->color(fn($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
                                        TextEntry::make('departments_represented')
                                        ->label('Departments Represented')
                                        ->getStateUsing(fn($record) => $record->participants()->with('user.department')->get()->pluck('user.department.name')->filter()->unique()->count())
                                        ->badge()
                                        ->color('info'),
                                        TextEntry::make('assessment_categories_count')
                                        ->label('Assessment Categories')
                                        ->getStateUsing(fn($record) => $record->assessmentCategories()->count())
                                        ->badge()
                                        ->color('warning'),
                                    ]),
                                ]),
                            ])
                            ->collapsible(),
                            Section::make('Mentorship Materials & Resources')
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
                                TextEntry::make('total_material_cost')
                                ->label('Total Material Cost')
                                ->getStateUsing(fn($record) => 'KES ' . number_format($record->trainingMaterials->sum('total_cost'), 2))
                                ->badge()
                                ->color('primary')
                                ->visible(fn() => $this->record->trainingMaterials->isNotEmpty()),
                            ])
                            ->collapsible(),
                            Section::make('Additional Information')
                            ->schema([
                                TextEntry::make('notes')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                    TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                    TextEntry::make('facility_assessment_status')
                                    ->label('Facility Assessment')
                                    ->getStateUsing(function ($record) {
                                        $assessment = \App\Models\FacilityAssessment::where('facility_id', $record->facility_id)
                                                ->where('status', 'approved')
                                                ->where('next_assessment_due', '>', now())
                                                ->latest()
                                                ->first();

                                        return $assessment ? 'Valid until ' . $assessment->next_assessment_due->format('M j, Y') : 'No valid assessment';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        $assessment = \App\Models\FacilityAssessment::where('facility_id', $record->facility_id)
                                                ->where('status', 'approved')
                                                ->where('next_assessment_due', '>', now())
                                                ->latest()
                                                ->first();

                                        return $assessment ? 'success' : 'danger';
                                    }),
                                ]),
                            ])
                            ->collapsible(),
        ]);
    }

    private function getSmartInsights(): array {
        $insights = [];

        $totalMentees = $this->record->participants()->count();
        $totalCategories = $this->record->assessmentCategories()->count();

        if ($totalMentees == 0) {
            $insights[] = [
                'type' => 'info',
                'title' => 'No Mentees Enrolled',
                'message' => 'Add mentees to begin the mentorship program.',
                'action' => 'Use the "Manage Mentees" button to add participants'
            ];
        }

        if ($totalCategories == 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'No Assessment Categories',
                'message' => 'Configure assessment categories to evaluate mentees.',
                'action' => 'Edit the training to add assessment categories'
            ];
        }

        // Training content insights
        if ($this->record->programs->isEmpty()) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'No Programs Selected',
                'message' => 'Link this mentorship to training programs for better structure.',
                'action' => 'Edit training to add programs and modules'
            ];
        }

        if ($this->record->trainingMaterials->isEmpty()) {
            $insights[] = [
                'type' => 'info',
                'title' => 'No Materials Planned',
                'message' => 'Consider adding training materials for better resource tracking.',
                'action' => 'Add materials to track costs and usage'
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

    private function exportTrainingSummary() {
        $filename = "mentorship_summary_{$this->record->identifier}_" . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () {
                    $file = fopen('php://output', 'w');

                    // Training Overview
                    fputcsv($file, ['MENTORSHIP TRAINING SUMMARY']);
                    fputcsv($file, ['Generated on: ' . now()->format('Y-m-d H:i:s')]);
                    fputcsv($file, ['']);

                    // Basic Information
                    fputcsv($file, ['BASIC INFORMATION']);
                    fputcsv($file, ['Training Title', $this->record->title]);
                    fputcsv($file, ['Training ID', $this->record->identifier]);
                    fputcsv($file, ['Facility', $this->record->facility->name]);
                    fputcsv($file, ['Status', ucfirst($this->record->status)]);
                    fputcsv($file, ['Start Date', $this->record->start_date?->format('Y-m-d')]);
                    fputcsv($file, ['End Date', $this->record->end_date?->format('Y-m-d')]);
                    fputcsv($file, ['Lead Mentor', $this->record->mentor ? "{$this->record->mentor->first_name} {$this->record->mentor->last_name}" : 'Not assigned']);
                    fputcsv($file, ['Coordinator', $this->record->organizer ? "{$this->record->organizer->first_name} {$this->record->organizer->last_name}" : 'Not assigned']);
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
                        fputcsv($file, ['Methodology', 'Description', 'Status']);
                        foreach ($this->record->methodologies as $methodology) {
                            fputcsv($file, [
                                $methodology->name,
                                $methodology->description ?? 'N/A',
                                $methodology->is_active ? 'Active' : 'Inactive'
                            ]);
                        }
                        fputcsv($file, ['']);
                    }

                    // Basic Statistics
                    fputcsv($file, ['BASIC STATISTICS']);
                    fputcsv($file, ['Total Mentees', $this->record->participants()->count()]);
                    fputcsv($file, ['Assessment Categories', $this->record->assessmentCategories()->count()]);
                    fputcsv($file, ['']);

                    // Assessment Categories
                    if ($this->record->assessmentCategories->isNotEmpty()) {
                        fputcsv($file, ['ASSESSMENT CATEGORIES']);
                        fputcsv($file, ['Category', 'Weight (%)', 'Pass Threshold (%)', 'Method']);
                        foreach ($this->record->assessmentCategories as $category) {
                            fputcsv($file, [
                                $category->name,
                                $category->pivot->weight_percentage,
                                $category->pivot->pass_threshold,
                                $category->assessment_method,
                            ]);
                        }
                        fputcsv($file, ['']);
                    }

                    // Materials Summary
                    if ($this->record->trainingMaterials->isNotEmpty()) {
                        fputcsv($file, ['MATERIALS SUMMARY']);
                        fputcsv($file, ['Material', 'Planned Qty', 'Used Qty', 'Cost', 'Status']);
                        foreach ($this->record->trainingMaterials as $material) {
                            fputcsv($file, [
                                $material->inventoryItem->name,
                                $material->quantity_planned,
                                $material->quantity_used ?? 0,
                                'KES ' . number_format($material->total_cost, 2),
                                $this->getMaterialStatus($material)
                            ]);
                        }
                    }

                    fclose($file);
                }, $filename, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
    
       
    public function getTitle(): string{
        return 'View Mentorship';
    }

}
