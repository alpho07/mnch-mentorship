<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Training extends Model {

    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'type', // 'global_training' or 'facility_mentorship'
        'lead_type', // 'national', 'county', 'partner'
        'status',
        'identifier',
        'program_id', // Keep for backward compatibility
        'facility_id', // nullable for global trainings
        'lead_county_id', // for county-led trainings
        'lead_partner_id', // for partner-led trainings
        'organizer_id',
        'mentor_id',
        //'location',
        'start_date',
        'end_date',
        'registration_deadline',
        'max_participants',
        'target_audience',
        'completion_criteria',
        'materials_needed',
        'learning_outcomes',
        'prerequisites',
        'training_approaches', // Array of approaches
        'notes',
        'assess_participants', // Boolean - whether to assess participants
        'provide_materials', // Boolean - whether to provide materials
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_deadline' => 'datetime',
        'materials_needed' => 'array',
        'learning_outcomes' => 'array',
        'prerequisites' => 'array',
        'training_approaches' => 'array',
        'assess_participants' => 'boolean',
        'provide_materials' => 'boolean',
    ];

    // Relationships
    public function program(): BelongsTo {
        return $this->belongsTo(Program::class);
    }

    public function programs(): BelongsToMany {
        return $this->belongsToMany(Program::class, 'training_programs', 'training_id', 'program_id');
    }

    public function facility(): BelongsTo {
        return $this->belongsTo(Facility::class);
    }

    public function county(): BelongsTo {
        return $this->belongsTo(County::class, 'lead_county_id');
    }

    public function partner(): BelongsTo {
        return $this->belongsTo(Partner::class, 'lead_partner_id');
    }

    public function division(): BelongsTo {
        return $this->belongsTo(Division::class, 'lead_division_id');
    }

    public function organizer(): BelongsTo {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function mentor(): BelongsTo {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function participants(): HasMany {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function sessions(): HasMany {
        return $this->hasMany(TrainingSession::class);
    }

    public function departments(): BelongsToMany {
        return $this->belongsToMany(Department::class, 'training_departments');
    }

    public function modules(): BelongsToMany {
        return $this->belongsToMany(Module::class, 'training_modules');
    }

    public function methodologies(): BelongsToMany {
        return $this->belongsToMany(Methodology::class, 'training_methodologies');
    }

    public function targetFacilities(): BelongsToMany {
        return $this->belongsToMany(Facility::class, 'training_target_facilities', 'training_id', 'facility_id');
    }

    public function trainingMaterials(): HasMany {
        return $this->hasMany(TrainingMaterial::class);
    }

    public function assessmentCategories(): BelongsToMany {
        return $this->belongsToMany(AssessmentCategory::class, 'training_assessment_categories')
                        ->withPivot([
                            'weight_percentage',
                            'pass_threshold',
                            'is_required',
                            'order_sequence',
                            'is_active'
                        ])
                        ->withTimestamps()
                        ->wherePivot('is_active', true)
                        ->orderByPivot('order_sequence');
    }

    // Helper Methods
    public function isGlobalTraining(): bool {
        return $this->type === 'global_training';
    }

    public function isFacilityMentorship(): bool {
        return $this->type === 'facility_mentorship';
    }

    public function isNationalLed(): bool {
        return $this->lead_type === 'national';
    }

    public function isCountyLed(): bool {
        return $this->lead_type === 'county';
    }

    public function isPartnerLed(): bool {
        return $this->lead_type === 'partner';
    }

    public function getLeadOrganizationAttribute(): string {
        return match ($this->lead_type) {
            'national' => 'Ministry of Health',
            'county' => $this->county?->name ?? 'County not specified',
            'partner' => $this->partner?->name ?? 'Partner not specified',
            default => 'Not specified',
        };
    }

    public function getCompletionRateAttribute(): float {
        $total = $this->participants()->count();
        if ($total === 0)
            return 0;

        $completed = $this->participants()
                ->where('completion_status', 'completed')
                ->count();

        return round(($completed / $total) * 100, 2);
    }

    public function getAverageScoreAttribute(): float {
        return $this->participants()
                        ->join('participant_objective_results', 'training_participants.id', '=', 'participant_objective_results.participant_id')
                        ->avg('participant_objective_results.score') ?? 0;
    }

    public function getTotalMaterialCostAttribute(): float {
        return $this->trainingMaterials()->sum('total_cost') ?? 0;
    }

    public function getActualMaterialCostAttribute(): float {
        return $this->trainingMaterials()
                        ->selectRaw('SUM(quantity_used * unit_cost) as actual_cost')
                        ->value('actual_cost') ?? 0;
    }

    public function getMaterialUtilizationRateAttribute(): float {
        $totalPlanned = $this->trainingMaterials()->sum('quantity_planned');
        $totalUsed = $this->trainingMaterials()->sum('quantity_used');

        return $totalPlanned > 0 ? round(($totalUsed / $totalPlanned) * 100, 1) : 0;
    }

    // Scopes
    public function scopeGlobalTrainings($query) {
        return $query->where('type', 'global_training');
    }

    public function scopeFacilityMentorships($query) {
        return $query->where('type', 'facility_mentorship');
    }

    public function scopeNationalLed($query) {
        return $query->where('lead_type', 'national');
    }

    public function scopeCountyLed($query) {
        return $query->where('lead_type', 'county');
    }

    public function scopePartnerLed($query) {
        return $query->where('lead_type', 'partner');
    }

    public function scopeRegistrationOpen($query) {
        return $query->where('status', 'registration_open')
                        ->where('registration_deadline', '>=', now());
    }

    public function scopeOngoing($query) {
        return $query->where('status', 'ongoing');
    }

    public function scopeUpcoming($query) {
        return $query->where('start_date', '>', now());
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'completed');
    }

    // Analytics methods (OPTIMIZED)
    public function getParticipantsByFacility(): Collection {
        return $this->participants()
                        ->join('users', 'training_participants.user_id', '=', 'users.id')
                        ->join('facilities', 'users.facility_id', '=', 'facilities.id')
                        ->groupBy('facilities.name')
                        ->selectRaw('facilities.name, COUNT(*) as count')
                        ->pluck('count', 'name');
    }

    public function getParticipantsByCadre(): Collection {
        return $this->participants()
                        ->join('users', 'training_participants.user_id', '=', 'users.id')
                        ->join('cadres', 'users.cadre_id', '=', 'cadres.id')
                        ->groupBy('cadres.name')
                        ->selectRaw('cadres.name, COUNT(*) as count')
                        ->pluck('count', 'name');
    }

    public function getCompletionStats(): array {
        $stats = $this->participants()
                ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN completion_status = "completed" THEN 1 END) as completed,
                COUNT(CASE WHEN completion_status = "in_progress" THEN 1 END) as in_progress
            ')
                ->first();

        return [
            'total' => $stats->total,
            'completed' => $stats->completed,
            'in_progress' => $stats->in_progress,
            'completion_rate' => $stats->total > 0 ? round(($stats->completed / $stats->total) * 100, 2) : 0,
        ];
    }

    // Relationship assignment methods
    public function assignPrograms(array $programIds): void {
        $this->programs()->sync($programIds);
    }

    public function assignModules(array $moduleIds): void {
        $this->modules()->sync($moduleIds);
    }

    public function assignMethodologies(array $methodologyIds): void {
        $this->methodologies()->sync($methodologyIds);
    }

    // Material management methods
    public function addMaterial(int $inventoryItemId, int $quantity, ?string $notes = null): TrainingMaterial {
        $inventoryItem = InventoryItem::findOrFail($inventoryItemId);

        return $this->trainingMaterials()->create([
                    'inventory_item_id' => $inventoryItemId,
                    'quantity_planned' => $quantity,
                    'unit_cost' => $inventoryItem->unit_price,
                    'total_cost' => $quantity * $inventoryItem->unit_price,
                    'usage_notes' => $notes,
        ]);
    }

    public function updateMaterialUsage(int $materialId, int $quantityUsed, ?int $returnedQuantity = null): bool {
        $material = $this->trainingMaterials()->find($materialId);

        if (!$material) {
            return false;
        }

        return $material->update([
                    'quantity_used' => $quantityUsed,
                    'returned_quantity' => $returnedQuantity,
        ]);
    }

    public function getMaterialWastage(): Collection {
        return $this->trainingMaterials()
                        ->with('inventoryItem')
                        ->get()
                        ->filter(fn($material) => $material->wastage_quantity > 0)
                        ->map(fn($material) => [
                            'material' => $material->inventoryItem->name,
                            'wastage_quantity' => $material->wastage_quantity,
                            'wastage_cost' => $material->wastage_quantity * $material->unit_cost,
        ]);
    }

    // Assessment category methods
    public function attachAssessmentCategories(array $categories): void {
        $attachData = [];

        foreach ($categories as $categoryId => $settings) {
            $category = AssessmentCategory::find($categoryId);
            if (!$category)
                continue;

            $attachData[$categoryId] = [
                'weight_percentage' => $settings['weight_percentage'] ?? $category->default_weight_percentage,
                'pass_threshold' => $settings['pass_threshold'] ?? 70.00,
                'is_required' => $settings['is_required'] ?? $category->is_required,
                'order_sequence' => $settings['order_sequence'] ?? $category->order_sequence,
                'is_active' => $settings['is_active'] ?? true,
            ];
        }

        $this->assessmentCategories()->sync($attachData);
    }

    public function updateCategorySettings(int $categoryId, array $settings): bool {
        return $this->assessmentCategories()->updateExistingPivot($categoryId, $settings);
    }

    public function getCategoryWeight(int $categoryId): ?float {
        $category = $this->assessmentCategories()->find($categoryId);
        return $category?->pivot->weight_percentage;
    }

    public function locations(): BelongsToMany {
        return $this->belongsToMany(Location::class, 'training_locations', 'training_id', 'location_id');
    }

    public function validateCategoryWeights(): array {
        $totalWeight = $this->assessmentCategories()->sum('training_assessment_categories.weight_percentage');

        return [
            'total_weight' => $totalWeight,
            'is_valid' => abs($totalWeight - 100.0) < 0.1,
            'difference' => $totalWeight - 100.0,
        ];
    }

    // Assessment calculation methods
    public function calculateOverallScore(TrainingParticipant $participant): array {
        $categories = $this->assessmentCategories;
        $results = $participant->assessmentResults->keyBy('assessment_category_id');

        $totalWeight = 0;
        $achievedWeight = 0;
        $allAssessed = true;
        $requiredPassed = true;

        foreach ($categories as $category) {
            $result = $results->get($category->id);
            $categoryWeight = $category->pivot->weight_percentage;

            if (!$result) {
                $allAssessed = false;
                if ($category->pivot->is_required) {
                    $requiredPassed = false;
                }
                continue;
            }

            $totalWeight += $categoryWeight;

            if ($result->result === 'pass') {
                $achievedWeight += $categoryWeight;
            } else if ($category->pivot->is_required) {
                $requiredPassed = false;
            }
        }

        $overallScore = $totalWeight > 0 ? round(($achievedWeight / $totalWeight) * 100, 1) : 0;

        if (!$allAssessed) {
            $status = 'INCOMPLETE';
        } elseif (!$requiredPassed) {
            $status = 'FAILED';
        } elseif ($overallScore >= 70) {
            $status = 'PASSED';
        } else {
            $status = 'FAILED';
        }

        return [
            'score' => $overallScore,
            'status' => $status,
            'assessed_categories' => $results->count(),
            'total_categories' => $categories->count(),
            'all_assessed' => $allAssessed,
            'required_passed' => $requiredPassed,
            'total_weight' => $totalWeight,
            'achieved_weight' => $achievedWeight,
        ];
    }

    public function getAssessmentSummary(): array {
        $participants = $this->participants()->with('assessmentResults')->get();
        $totalCategories = $this->assessmentCategories()->count();

        $passed = 0;
        $failed = 0;
        $incomplete = 0;
        $totalScore = 0;
        $assessedParticipants = 0;
        $completedAssessments = 0;

        foreach ($participants as $participant) {
            $calculation = $this->calculateOverallScore($participant);
            $completedAssessments += $calculation['assessed_categories'];

            if ($calculation['all_assessed']) {
                $assessedParticipants++;
                $totalScore += $calculation['score'];

                if ($calculation['status'] === 'PASSED') {
                    $passed++;
                } else {
                    $failed++;
                }
            } else {
                $incomplete++;
            }
        }

        $totalPossible = $participants->count() * $totalCategories;

        return [
            'total_mentees' => $participants->count(),
            'total_categories' => $totalCategories,
            'passed_mentees' => $passed,
            'failed_mentees' => $failed,
            'incomplete_mentees' => $incomplete,
            'completion_rate' => $totalPossible > 0 ? round(($completedAssessments / $totalPossible) * 100, 1) : 0,
            'pass_rate' => $assessedParticipants > 0 ? round(($passed / $assessedParticipants) * 100, 1) : 0,
            'average_score' => $assessedParticipants > 0 ? round($totalScore / $assessedParticipants, 1) : 0,
        ];
    }
    
    public function hasAssessments(): bool
{
    return $this->assess_participants === true || $this->assessmentCategories()->exists();
}

/**
 * Check if this training has materials planning enabled
 */
public function hasMaterials(): bool
{
    return $this->provide_materials === true || $this->trainingMaterials()->exists();
}

/**
 * Get assessment status for the training
 */
public function getAssessmentStatusAttribute(): string
{
    if (!$this->hasAssessments()) {
        return 'Not Configured';
    }
    
    if ($this->assessmentCategories()->count() === 0) {
        return 'No Categories';
    }
    
    $totalWeight = $this->assessmentCategories->sum('pivot.weight_percentage');
    if (abs($totalWeight - 100) >= 0.1) {
        return 'Invalid Weights';
    }
    
    return 'Configured';
}

/**
 * Get materials status for the training
 */
public function getMaterialsStatusAttribute(): string
{
    if (!$this->hasMaterials()) {
        return 'Not Planned';
    }
    
    if ($this->trainingMaterials()->count() === 0) {
        return 'No Materials';
    }
    
    return 'Materials Planned';
}

/**
 * Scope to filter trainings with assessments
 */
public function scopeWithAssessments($query)
{
    return $query->where('assess_participants', true)
                 ->orWhereHas('assessmentCategories');
}

/**
 * Scope to filter trainings with materials
 */
public function scopeWithMaterials($query)
{
    return $query->where('provide_materials', true)
                 ->orWhereHas('trainingMaterials');
}

/**
 * Get training features summary
 */
public function getFeaturesSummaryAttribute(): array
{
    return [
        'has_programs' => $this->programs()->exists(),
        'has_modules' => $this->modules()->exists(),
        'has_methodologies' => $this->methodologies()->exists(),
        'has_assessments' => $this->hasAssessments(),
        'has_materials' => $this->hasMaterials(),
        'has_participants' => $this->participants()->exists(),
        'assessment_status' => $this->assessment_status,
        'materials_status' => $this->materials_status,
    ];
}
}
