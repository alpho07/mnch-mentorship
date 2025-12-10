<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Traits\HasSectionNavigation;
use App\Models\MainCadre;
use App\Models\HumanResourceResponse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditHumanResources extends EditRecord {

    use HasSectionNavigation;

    protected static string $resource = AssessmentResource::class;

    public function mount(int|string $record): void {
        parent::mount($record);
        $this->form->fill($this->loadSavedResponses());
    }

    protected function loadSavedResponses(): array {
        $responses = HumanResourceResponse::where('assessment_id', $this->record->id)->get();

        $data = [];

        foreach ($responses as $response) {
            $prefix = "hr_{$response->cadre_id}_";
            $data["{$prefix}total_in_facility"] = $response->total_in_facility;
            $data["{$prefix}etat_plus"] = $response->etat_plus;
            $data["{$prefix}comprehensive_newborn_care"] = $response->comprehensive_newborn_care;
            $data["{$prefix}imnci"] = $response->imnci;
            $data["{$prefix}type_1_diabetes"] = $response->type_1_diabetes;
            $data["{$prefix}essential_newborn_care"] = $response->essential_newborn_care;
        }

        return $data;
    }

    protected function updateTotal(int $cadreId, Forms\Set $set): void {
        $prefix = "hr_{$cadreId}_";

        $etat = (int) $this->data["{$prefix}etat_plus"] ?? 0;
        $comprehensive = (int) $this->data["{$prefix}comprehensive_newborn_care"] ?? 0;
        $imnci = (int) $this->data["{$prefix}imnci"] ?? 0;
        $diabetes = (int) $this->data["{$prefix}type_1_diabetes"] ?? 0;
        $essential = (int) $this->data["{$prefix}essential_newborn_care"] ?? 0;

        $total = $etat + $comprehensive + $imnci + $diabetes + $essential;

        $set("{$prefix}total_in_facility", $total);
    }

    public function form(Form $form): Form {
        $cadres = MainCadre::where('is_active', true)
                ->orderBy('order')
                ->get();

        return $form->schema([
                            Forms\Components\Section::make('Human Resources Assessment')
                            ->description('Enter staff counts and training status for each cadre')
                            ->schema(
                                    $cadres->map(function ($cadre) {
                                        return Forms\Components\Section::make($cadre->name)
                                                        ->schema([
                                                            Forms\Components\Grid::make(6)
                                                            ->schema([
                                                                Forms\Components\TextInput::make("hr_{$cadre->id}_etat_plus")
                                                                ->label('ETAT+')
                                                                ->numeric()
                                                                ->integer()
                                                                ->minValue(0)
                                                                ->default(0)
                                                                ->required()
                                                                ->live(onBlur: true)
                                                                ->afterStateUpdated(fn($state, Forms\Set $set) =>
                                                                        $this->updateTotal($cadre->id, $set)
                                                                ),
                                                                Forms\Components\TextInput::make("hr_{$cadre->id}_comprehensive_newborn_care")
                                                                ->label('Comprehensive Newborn Care')
                                                                ->numeric()
                                                                ->integer()
                                                                ->minValue(0)
                                                                ->default(0)
                                                                ->required()
                                                                ->live(onBlur: true)
                                                                ->afterStateUpdated(fn($state, Forms\Set $set) =>
                                                                        $this->updateTotal($cadre->id, $set)
                                                                ),
                                                                Forms\Components\TextInput::make("hr_{$cadre->id}_imnci")
                                                                ->label('IMNCI')
                                                                ->numeric()
                                                                ->integer()
                                                                ->minValue(0)
                                                                ->default(0)
                                                                ->required()
                                                                ->live(onBlur: true)
                                                                ->afterStateUpdated(fn($state, Forms\Set $set) =>
                                                                        $this->updateTotal($cadre->id, $set)
                                                                ),
                                                                Forms\Components\TextInput::make("hr_{$cadre->id}_type_1_diabetes")
                                                                ->label('Type 1 Diabetes')
                                                                ->numeric()
                                                                ->integer()
                                                                ->minValue(0)
                                                                ->default(0)
                                                                ->required()
                                                                ->live(onBlur: true)
                                                                ->afterStateUpdated(fn($state, Forms\Set $set) =>
                                                                        $this->updateTotal($cadre->id, $set)
                                                                ),
                                                                Forms\Components\TextInput::make("hr_{$cadre->id}_essential_newborn_care")
                                                                ->label('Essential Newborn Care')
                                                                ->numeric()
                                                                ->integer()
                                                                ->minValue(0)
                                                                ->default(0)
                                                                ->required()
                                                                ->live(onBlur: true)
                                                                ->afterStateUpdated(fn($state, Forms\Set $set) =>
                                                                        $this->updateTotal($cadre->id, $set)
                                                                ),
                                                                Forms\Components\TextInput::make("hr_{$cadre->id}_total_in_facility")
                                                                ->label('Total Trained')
                                                                ->numeric()
                                                                ->integer()
                                                                ->default(0)
                                                                ->disabled()
                                                                ->dehydrated()
                                                                ->extraAttributes(['class' => 'font-bold'])
                                                                ->prefixIcon('heroicon-o-calculator')
                                                                ->prefixIconColor('success'),
                                                            ])
                                                            ->columns(6),
                                                        ])
                                                        ->collapsible()
                                                        ->collapsed(false);
                                    })->toArray()
                            )
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array {
        $cadres = MainCadre::where('is_active', true)->get();

        foreach ($cadres as $cadre) {
            $prefix = "hr_{$cadre->id}_";

            if (!isset($data["{$prefix}total_in_facility"])) {
                continue;
            }

            HumanResourceResponse::updateOrCreate(
                    [
                        'assessment_id' => $this->record->id,
                        'cadre_id' => $cadre->id,
                    ],
                    [
                        'total_in_facility' => (int) ($data["{$prefix}total_in_facility"] ?? 0),
                        'etat_plus' => (int) ($data["{$prefix}etat_plus"] ?? 0),
                        'comprehensive_newborn_care' => (int) ($data["{$prefix}comprehensive_newborn_care"] ?? 0),
                        'imnci' => (int) ($data["{$prefix}imnci"] ?? 0),
                        'type_1_diabetes' => (int) ($data["{$prefix}type_1_diabetes"] ?? 0),
                        'essential_newborn_care' => (int) ($data["{$prefix}essential_newborn_care"] ?? 0),
                    ]
            );
        }

        $progress = $this->record->section_progress ?? [];
        $progress['human_resources'] = true;
        $this->record->section_progress = $progress;
        $this->record->save();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'hr_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    protected function getCurrentSectionKey(): string {
        return 'human_resources';
    }

    protected function getSavedNotification(): ?Notification {
        $nextSection = $this->getNextSection();

        return Notification::make()
                        ->title('Human Resources section saved successfully')
                        ->body($nextSection ? "Moving to: {$nextSection}" : "Returning to dashboard")
                        ->success()
                        ->duration(3000);
    }

    protected function getNextSection(): ?string {
        $sections = $this->getAllSections();
        $currentIndex = array_search('human_resources', array_keys($sections));
        $sectionKeys = array_keys($sections);

        for ($i = $currentIndex + 1; $i < count($sectionKeys); $i++) {
            if (!$sections[$sectionKeys[$i]]['done']) {
                return $sections[$sectionKeys[$i]]['label'];
            }
        }

        return null;
    }

    public function getTitle(): string {
        return "Human Resources - {$this->record->facility->name}";
    }

    public static function getNavigationLabel(): string {
        return 'Human Resources';
    }
}
