<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewGlobalTraining extends ViewRecord {

    protected static string $resource = GlobalTrainingResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\EditAction::make()
                    ->color('warning'),
                    Actions\Action::make('manage_participants')
                    ->label('Manage Participants')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->url(fn() => static::getResource()::getUrl('participants', ['record' => $this->record])),
                    /*Actions\Action::make('manage_assessments')
                    ->label('Manage Assessments')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->url(fn() => static::getResource()::getUrl('assessments', ['record' => $this->record])),*/
                    Actions\Action::make('export_participants')
                    ->label('Export Participants')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        return $this->exportTrainingParticipants();
                    }),
                    Actions\DeleteAction::make()
                    ->requiresConfirmation(),
        ];
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
                    'objectiveResults.objective',
                    'objectiveResults.grade',
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
                        'Overall Score (%)',
                        'Overall Grade',
                        'Pass/Fail Status',
                        'Objectives Assessed',
                        'Total Objectives',
                        'Assessment Progress (%)',
                        'Individual Objective Scores',
                        'Assessment Feedback',
                        'Final Outcome',
                        'Participant Notes'
                    ];

                    fputcsv($file, $headers);

                    $totalObjectives = \App\Models\Objective::where('training_id', $this->record->id)->count();

                    foreach ($participants as $participant) {
                        $user = $participant->user;
                        $facility = $user->facility;
                        $objectiveResults = $participant->objectiveResults;
                        $assessedObjectives = $objectiveResults->count();
                        $averageScore = $objectiveResults->avg('score');

                        // Individual objective scores
                        $individualScores = $objectiveResults->map(function ($result) {
                                    return "{$result->objective->objective_text}: {$result->score}% ({$result->grade?->name})";
                                })->implode('; ');

                        // Assessment feedback
                        $feedback = $objectiveResults->pluck('feedback')->filter()->implode('; ');

                        // Pass/Fail determination
                        $passFailStatus = 'Not Assessed';
                        if ($averageScore !== null) {
                            $passFailStatus = $averageScore >= 70 ? 'PASS' : 'FAIL';
                        }

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
                            $averageScore ? number_format($averageScore, 1) : 'Not Assessed',
                            $participant->overall_grade,
                            $passFailStatus,
                            $assessedObjectives,
                            $totalObjectives,
                            $totalObjectives > 0 ? number_format(($assessedObjectives / $totalObjectives) * 100, 1) : '0',
                            $individualScores,
                            $feedback,
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
                                ->placeholder('No description provided'),
                                Grid::make(2)
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
                                    TextEntry::make('location')
                                    ->icon('heroicon-o-map-pin'),
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
                                TextEntry::make('mentor.full_name')
                                ->label('Created By')
                                ->icon('heroicon-o-user')
                                ->helperText(fn($record): string => $record->mentor?->facility?->name ?? 'No facility'),
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
                                    TextEntry::make('max_participants')
                                    ->label('Maximum Participants')
                                    ->icon('heroicon-o-users')
                                    ->suffix(' people'),
                                ]),
                            ]),
                            Section::make('Content & Programs')
                            ->schema([
                                TextEntry::make('programs.name')
                                ->label('Programs')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('info'),
                                TextEntry::make('modules.name')
                                ->label('Modules')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('success')
                                ->formatStateUsing(function ($state, $record) {
                                    return $record->modules->map(function ($module) {
                                                return "{$module->program->name} - {$module->name}";
                                            })->toArray();
                                }),
                                TextEntry::make('methodologies.name')
                                ->label('Methodologies')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('warning'),
                            ]),
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
                                    ->getStateUsing(fn($record) => $record->participants()->with('user.facility')->get()->pluck('user.facility.name')->unique()->count())
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
                                ->placeholder('No additional notes')
                                ->columnSpanFull(),
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
}
