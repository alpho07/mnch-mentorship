<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessment extends CreateRecord {

    protected static string $resource = AssessmentResource::class;

    /**
     * Step 1 only — UI form schema
     */
    public function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Facility Information')
                            ->schema([
                                Forms\Components\Select::make('county_filter')
                                ->label('County')
                                ->options(\App\Models\County::pluck('name', 'id'))
                                ->searchable()
                                ->live()
                                ->dehydrated(false),
                                Forms\Components\Select::make('facility_id')
                                ->label('Facility')
                                ->required()
                                ->searchable()
                                ->live()
                                ->options(function (Forms\Get $get) {
                                    $countyId = $get('county_filter');
                                    $query = Facility::query();

                                    if ($countyId) {
                                        $query->whereHas('subcounty', function ($q) use ($countyId) {
                                            $q->where('county_id', $countyId);
                                        });
                                    }

                                    return $query->get()->mapWithKeys(function ($facility) {
                                                $label = ($facility->mfl_code ? "{$facility->mfl_code} - " : "") . $facility->name;
                                                return [$facility->id => $label];
                                            });
                                })
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state) {
                                        $facility = Facility::with(['subcounty.county'])->find($state);
                                        if ($facility) {
                                            $set('facility_info', [
                                                'mfl_code' => $facility->mfl_code ?? 'N/A',
                                                'level' => $facility->level ?? 'N/A',
                                                'ownership' => $facility->ownership ?? 'N/A',
                                                'county' => $facility->subcounty->county->name ?? 'N/A',
                                                'subcounty' => $facility->subcounty->name ?? 'N/A',
                                                'contact' => $facility->phone ?? $facility->email ?? 'N/A',
                                            ]);
                                        }
                                    }
                                }),
                                Forms\Components\Placeholder::make('facility_info')
                                ->label('')
                                ->content(function (Forms\Get $get) {
                                    $info = $get('facility_info');
                                    if (!$info) {
                                        return 'Select a facility to see details';
                                    }
                                    return view('filament.components.facility-info', ['info' => $info]);
                                })
                                ->dehydrated(false),
                            ])
                            ->columns(2),
                            Forms\Components\Section::make('Assessment Details')
                            ->schema([
                                Forms\Components\Select::make('assessment_type')
                                ->label('Assessment Type')
                                ->options([
                                    'baseline' => 'Baseline',
                                    'midline' => 'Midline',
                                    'endline' => 'Endline',
                                ])
                                ->default('baseline')
                                ->required(),
                                Forms\Components\DatePicker::make('assessment_date')
                                ->label('Assessment Date')
                                ->default(now())
                                ->required(),
                            ])
                            ->columns(2),
                            Forms\Components\Section::make('Assessor Information')
                            ->description('Auto-populated from logged-in user')
                            ->schema([
                                Forms\Components\TextInput::make('assessor_name')
                                ->label('Assessor Name')
                                ->default(auth()->user()->name ?? '')
                                ->disabled()
                                ->dehydrated(),
                                Forms\Components\TextInput::make('assessor_contact')
                                ->label('Assessor Contact')
                                ->default(auth()->user()->email ?? auth()->user()->phone ?? '')
                                ->disabled()
                                ->dehydrated(),
                            ])
                            ->columns(2),
        ]);
    }

    /**
     * Hook: Mutate data before save (add assessor_id and progress array)
     */
    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['assessor_id'] = auth()->id();
        $data['status'] = 'in_progress';

        // Section progress initialization
        $data['section_progress'] = [
            'facility_assessor' => true, // Step 1 completed
            'infrastructure' => false,
            'skills_lab' => false,
            'human_resources' => false,
            'health_products' => false,
            'information_systems' => false,
            'quality_of_care' => false,
        ];

        return $data;
    }

    /**
     * After saving Step 1 → redirect back to list
     */
    protected function getRedirectUrl(): string {
        return static::getResource()::getUrl();
    }

    protected function getCreatedNotification(): ?Notification {
        return Notification::make()
                        ->success()
                        ->title('Assessment created')
                        ->body('Continue from the dashboard to complete all sections.');
    }
}
