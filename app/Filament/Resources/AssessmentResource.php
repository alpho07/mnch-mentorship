<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentResource\Pages;
use App\Models\Assessment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssessmentResource extends Resource {

    protected static ?string $model = Assessment::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Assessments';
    protected static ?string $navigationGroup = 'Facility Assessment';
    protected static ?int $navigationSort = 1;

    /**
     * No form() needed here because each section has its own page.
     */
    public static function form(Form $form): Form {
        return $form; // Empty — pages define their own forms
    }

    /**
     * Table remains EXACTLY as your original resource had.
     */
    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('facility.name')->label('Facility'),
                            Tables\Columns\TextColumn::make('facility.mfl_code')->label('MFL Code'),
                            Tables\Columns\BadgeColumn::make('assessment_type')->label('Type'),
                            Tables\Columns\TextColumn::make('assessment_date')->label('Date')->date(),
                            Tables\Columns\TextColumn::make('assessor_name')->label('Assessor'),
                            Tables\Columns\BadgeColumn::make('status')->label('Status'),
                        ])
                        ->filters([])
                        ->actions([
                            Tables\Actions\Action::make('Dashboard')
                            ->label('Continue')
                            ->icon('heroicon-o-arrow-right-circle')
                            ->url(fn($record) => static::getUrl('dashboard', ['record' => $record]))
                            ->color('primary'),
                        ])
                        ->bulkActions([]);
    }

    /**
     * Register all pages including the new dashboard and section editors.
     */
    public static function getPages(): array {
        return [
            'index' => Pages\ListAssessments::route('/'),
            'create' => Pages\CreateAssessment::route('/create'),
            // NEW: Main dashboard after creation
            'dashboard' => Pages\AssessmentDashboard::route('/{record}/dashboard'),
            // NEW: Section editing pages
            'edit-infrastructure' => Pages\EditInfrastructure::route('/{record}/infrastructure'),
            'edit-skills-lab' => Pages\EditSkillsLab::route('/{record}/skills-lab'),
            'edit-human-resources' => Pages\EditHumanResources::route('/{record}/human-resources'),
            'edit-health-products' => Pages\EditHealthProducts::route('/{record}/health-products'),
            'edit-information-systems' => Pages\EditInformationSystems::route('/{record}/information-systems'),
            'edit-quality-of-care' => Pages\EditQualityOfCare::route('/{record}/quality-of-care'),
        ];
    }
}
