<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Services\AssessmentExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;

class ListAssessments extends ListRecords {

    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('export_all')
                    ->label('Export All to CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        $assessments = \App\Models\Assessment::with([
                                    'facility.subcounty.county',
                                    'sectionScores.section'
                                ])->get();

                        $service = app(AssessmentExportService::class);
                        $csv = $service->exportMultipleAssessments($assessments);

                        $filename = 'mnch-assessments-bulk-' . now()->format('Y-m-d') . '.csv';

                        return response()->streamDownload(function () use ($csv) {
                                    echo $csv;
                                }, $filename, [
                                    'Content-Type' => 'text/csv',
                        ]);
                    }),
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('facility.mfl_code')
                            ->label('MFL Code')
                            ->searchable(),
                            Tables\Columns\TextColumn::make('assessment_type')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'baseline' => 'info',
                                        'midline' => 'warning',
                                        'endline' => 'success',
                                        default => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('assessment_date')
                            ->date()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'draft' => 'gray',
                                        'in_progress' => 'warning',
                                        'completed' => 'success',
                                        default => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('overall_percentage')
                            ->label('Score')
                            ->formatStateUsing(fn($state) => $state ? number_format($state, 1) . '%' : 'N/A')
                            ->sortable(),
                            Tables\Columns\TextColumn::make('overall_grade')
                            ->label('Grade')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'green' => 'success',
                                        'yellow' => 'warning',
                                        'red' => 'danger',
                                        default => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('assessor_name')
                            ->label('Assessor')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                            ]),
                            Tables\Filters\SelectFilter::make('assessment_type')
                            ->options([
                                'baseline' => 'Baseline',
                                'midline' => 'Midline',
                                'endline' => 'Endline',
                            ]),
                            Tables\Filters\SelectFilter::make('overall_grade')
                            ->label('Grade')
                            ->options([
                                'green' => 'Green',
                                'yellow' => 'Yellow',
                                'red' => 'Red',
                            ]),
                        ])
                        ->actions([
                            // View Summary Action
                            Tables\Actions\Action::make('view_summary')
                            ->label('View')
                            ->icon('heroicon-o-eye')
                            ->color('info')
                            ->url(fn($record) => AssessmentResource::getUrl('view', ['record' => $record])),
                            // Dashboard Action
                            Tables\Actions\Action::make('dashboard')
                            ->label('Continue')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->color('primary')
                            ->visible(fn($record) => $record->status !== 'completed')
                            ->url(fn($record) => AssessmentResource::getUrl('dashboard', ['record' => $record])),
                            // Export Single CSV Action
                            Tables\Actions\Action::make('export_csv')
                            ->label('CSV')
                            ->icon('heroicon-o-document-arrow-down')
                            ->color('success')
                            ->action(function ($record) {
                                $service = app(AssessmentExportService::class);
                                $csv = $service->exportAssessmentToCSV($record);

                                $filename = sprintf(
                                        'mnch-assessment-%s-%s.csv',
                                        $record->facility->name,
                                        $record->assessment_date->format('Y-m-d')
                                );

                                return response()->streamDownload(function () use ($csv) {
                                            echo $csv;
                                        }, $filename, [
                                            'Content-Type' => 'text/csv',
                                ]);
                            }),
                            // Download PDF Action
                            Tables\Actions\Action::make('download_pdf')
                            ->label('PDF')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('warning')
                            ->visible(fn($record) => $record->status === 'completed')
                            ->action(function ($record) {
                                $service = app(\App\Services\AssessmentPdfReportService::class);
                                $pdf = $service->generateExecutiveReport($record);

                                $filename = sprintf(
                                        'MNCH-Assessment-%s-%s.pdf',
                                        $record->facility->name,
                                        $record->assessment_date->format('Y-m-d')
                                );

                                return response()->streamDownload(function () use ($pdf) {
                                            echo $pdf->output();
                                        }, $filename);
                            }),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('export_selected')
                                ->label('Export Selected to CSV')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('success')
                                ->action(function ($records) {
                                    $service = app(AssessmentExportService::class);
                                    $csv = $service->exportMultipleAssessments($records);

                                    $filename = 'mnch-assessments-selected-' . now()->format('Y-m-d') . '.csv';

                                    return response()->streamDownload(function () use ($csv) {
                                                echo $csv;
                                            }, $filename, [
                                                'Content-Type' => 'text/csv',
                                    ]);
                                }),
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ])
                        ->defaultSort('created_at', 'desc');
    }
}
