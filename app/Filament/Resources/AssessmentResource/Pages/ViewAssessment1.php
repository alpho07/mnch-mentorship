<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Services\DynamicScoringService;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class ViewAssessmentSummary1 extends Page {

    protected static string $resource = AssessmentResource::class;
    protected static string $view = 'filament.pages.view-assessment-summary';
    public $assessment;
    public $summary;

    public function mount($record): void {
        $this->assessment = \App\Models\Assessment::with([
                    'facility',
                    'assessor',
                    'sectionScores.section',
                ])->findOrFail($record);

        $service = app(DynamicScoringService::class);
        $this->summary = $service->getAssessmentSummary($this->assessment->id);
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('edit')
                    ->label('Edit Assessment')
                    ->icon('heroicon-o-pencil')
                    ->url(fn() => static::getResource()::getUrl('edit', ['record' => $this->assessment])),
                    Actions\Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn() => $this->assessment->status === 'completed')
                    ->action(function () {
                        // TODO: Implement PDF generation
                        \Filament\Notifications\Notification::make()
                                ->title('PDF generation coming soon')
                                ->info()
                                ->send();
                    }),
                    Actions\Action::make('back')
                    ->label('Back to List')
                    ->icon('heroicon-o-arrow-left')
                    ->url(fn() => static::getResource()::getUrl('index')),
        ];
    }

    public function getTitle(): string {
        return 'Assessment Summary - ' . $this->assessment->facility->name;
    }
}
