<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentCommodityResponse;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditHealthProducts extends EditRecord {

    protected static string $resource = AssessmentResource::class;

    public function mount(int|string $record): void {
        parent::mount($record);

        // Load saved responses into the form
        $this->form->fill($this->loadSavedResponses());
    }

    protected function loadSavedResponses(): array {
        $responses = AssessmentCommodityResponse::where('assessment_id', $this->record->id)->get();

        $data = ['commodities' => []];

        foreach ($responses as $response) {
            $data['commodities'][$response->assessment_department_id][$response->commodity_id] = $response->available ? 1 : 0;
        }

        return $data;
    }

    public function form(Form $form): Form {
        // Load departments fresh each time form is called
        $departments = AssessmentDepartment::where('is_active', true)
                ->orderBy('order')
                ->get();

        // Load categories
        $categories = CommodityCategory::orderBy('order')->get();

        return $form->schema([
                            Forms\Components\Tabs::make('Departments')
                            ->tabs(
                                    $departments->map(function ($dept) use ($categories) {
                                        return Forms\Components\Tabs\Tab::make($dept->name)
                                                        ->schema($this->buildCategorySections($dept, $categories));
                                    })->toArray()
                            )
                            ->columnSpanFull()
                            ->contained(false),
        ]);
    }

    private function buildCategorySections($dept, $categories): array {
        return $categories->map(function ($category) use ($dept) {
                    // Get commodities for this category applicable to this department
                    $commodities = Commodity::where('commodity_category_id', $category->id)
                            ->where('is_active', true)
                            ->whereHas('applicableDepartments', function ($q) use ($dept) {
                                $q->where('assessment_department_id', $dept->id);
                            })
                            ->orderBy('order')
                            ->get();

                    if ($commodities->isEmpty()) {
                        return null;
                    }

                    return Forms\Components\Section::make($category->name)
                                    ->description("({$commodities->count()} items)")
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                        ->schema($commodities->map(function ($commodity) use ($dept) {
                                                    return Forms\Components\Grid::make(2)
                                                                    ->schema([
                                                                        Forms\Components\Placeholder::make("label_{$dept->id}_{$commodity->id}")
                                                                        ->label('')
                                                                        ->content($commodity->name)
                                                                        ->columnSpan(1),
                                                                        Forms\Components\ToggleButtons::make("commodities.{$dept->id}.{$commodity->id}")
                                                                        ->label('')
                                                                        ->options([
                                                                            1 => 'Available',
                                                                            0 => 'Not Available',
                                                                        ])
                                                                        ->colors([
                                                                            1 => 'success',
                                                                            0 => 'danger',
                                                                        ])
                                                                        ->icons([
                                                                            1 => 'heroicon-o-check-circle',
                                                                            0 => 'heroicon-o-x-circle',
                                                                        ])
                                                                        ->inline()
                                                                        ->columnSpan(1),
                                                                    ])
                                                                    ->columns(2);
                                                })->toArray())
                                    ])
                                    ->collapsible();
                })->filter()->values()->toArray();
    }

    protected function mutateFormDataBeforeSave(array $data): array {
        $payload = $data['commodities'] ?? [];

        foreach ($payload as $departmentId => $commodityEntries) {
            foreach ($commodityEntries as $commodityId => $value) {
                AssessmentCommodityResponse::updateOrCreate(
                        [
                            'assessment_id' => $this->record->id,
                            'assessment_department_id' => $departmentId,
                            'commodity_id' => $commodityId,
                        ],
                        [
                            'available' => (bool) $value,
                            'score' => (bool) $value ? 1 : 0,
                        ]
                );
            }

            // Recalculate department score
            app(\App\Services\CommodityScoringService::class)
                    ->recalculateDepartmentScore($this->record->id, $departmentId);
        }

        // Update progress
        $progress = $this->record->section_progress ?? [];
        $progress['health_products'] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        // Don't save commodities data to assessment table
        unset($data['commodities']);
        return $data;
    }

    protected function getRedirectUrl(): string {
        return AssessmentResource::getUrl('dashboard', ['record' => $this->record->id]);
    }

    protected function getSavedNotification(): ?Notification {
        return Notification::make()
                        ->title('Health Products saved successfully')
                        ->success();
    }

    public function getTitle(): string {
        return "Health Products - {$this->record->facility->name}";
    }
}
