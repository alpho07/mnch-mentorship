<?php

namespace App\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\MentorshipModuleUsage;
use App\Models\ProgramModule;
use App\Models\Training;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ModuleUsageService
 *
 * Enforces the domain invariant: a module can only be taught ONCE
 * per mentorship across all classes (UNIQUE mentorship_id + module_id).
 *
 * Also owns the auto-roster cascade: whenever a module is added to a class,
 * every currently enrolled mentee automatically gets a MenteeModuleProgress
 * row for it — zero manual work for the mentor.
 */
class ModuleUsageService {
    // ─────────────────────────────────────────────────────────────────────────
    // Read — available modules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Modules from the program that have NOT yet been used in any class
     * of this mentorship AND are not already in the current class.
     */
    public function getAvailableModules(Training $training, MentorshipClass $class): Collection {
        // IDs already used across the whole mentorship
        $usedIds = MentorshipModuleUsage::where('mentorship_id', $training->id)
                ->pluck('module_id')
                ->toArray();

        // IDs already in this specific class (guards against duplication before usage is recorded)
        $classIds = $class->classModules()->pluck('program_module_id')->toArray();

        $excludeIds = array_unique(array_merge($usedIds, $classIds));

        return ProgramModule::where('program_id', $training->program_id)
                        ->where('is_active', true)
                        ->whereNotIn('id', $excludeIds)
                        ->orderBy('order_sequence')
                        ->get();
    }

    /**
     * Usage summary for the subheading / info strips.
     */
    public function getUsageSummary(Training $training): array {
        $totalModules = ProgramModule::where('program_id', $training->program_id)
                ->where('is_active', true)
                ->count();

        $usedCount = MentorshipModuleUsage::where('mentorship_id', $training->id)->count();

        return [
            'total_modules' => $totalModules,
            'used_modules' => $usedCount,
            'remaining_modules' => max(0, $totalModules - $usedCount),
            'completion_percentage' => $totalModules > 0 ? round(($usedCount / $totalModules) * 100) : 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Write — assign modules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assign program modules to a class.
     *
     * For each module:
     *  1. Records usage in mentorship_module_usages (enforces unique-per-mentorship)
     *  2. Creates ClassModule record
     *  3. *** AUTO-ROSTER CASCADE ***
     *     If the class is already active, creates MenteeModuleProgress rows for
     *     every currently enrolled mentee — so the mentor never has to add mentees
     *     to modules manually.
     *
     * Returns the count of modules successfully created.
     */
    public function assignModulesToClass(
            Training $training,
            MentorshipClass $class,
            array $programModuleIds,
            ?int $recordedBy = null
    ): int {
        $added = 0;
        $recordedBy = $recordedBy ?? auth()->id();

        DB::transaction(function () use ($training, $class, $programModuleIds, $recordedBy, &$added) {
            $maxSequence = $class->classModules()->max('order_sequence') ?? 0;

            // Pre-fetch enrolled mentees once — used for cascade below
            $enrolledParticipants = $class->status === 'active' ? ClassParticipant::where('mentorship_class_id', $class->id)
                            ->whereIn('status', ['enrolled', 'active'])
                            ->get() : collect();

            // Pre-fetch previously completed module IDs per participant (for exemption)
            $completedByUser = [];
            foreach ($enrolledParticipants as $participant) {
                $completedByUser[$participant->id] = $this->getCompletedModuleIds($participant->user_id);
            }

            foreach ($programModuleIds as $programModuleId) {
                // Skip if already used in this mentorship
                $alreadyUsed = MentorshipModuleUsage::where('mentorship_id', $training->id)
                        ->where('module_id', $programModuleId)
                        ->exists();

                if ($alreadyUsed) {
                    Log::warning("ModuleUsageService: module {$programModuleId} already used in mentorship {$training->id}, skipping.");
                    continue;
                }

                // 1. Record usage
                MentorshipModuleUsage::create([
                    'mentorship_id' => $training->id,
                    'module_id' => $programModuleId,
                    'first_class_id' => $class->id,
                ]);

                // 2. Create ClassModule
                $maxSequence++;
                $classModule = ClassModule::create([
                    'mentorship_class_id' => $class->id,
                    'program_module_id' => $programModuleId,
                    'status' => 'not_started',
                    'order_sequence' => $maxSequence,
                    'min_attendance_percentage' => 75,
                    'requires_assessment' => false,
                    'attendance_link_active' => false,
                ]);

                // 3. AUTO-ROSTER CASCADE
                // If the class is already active, ensure every enrolled mentee
                // gets a progress row for this new module immediately.
                if ($enrolledParticipants->isNotEmpty()) {
                    $this->cascadeRosterToModule($classModule, $enrolledParticipants, $completedByUser);
                }

                $added++;
            }
        });

        return $added;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Write — remove modules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Remove a module from a class.
     *
     * Releases the usage record so the module becomes available for
     * other classes. Blocked if the module has started or has sessions.
     */
    public function removeModuleFromClass(
            Training $training,
            MentorshipClass $class,
            ClassModule $classModule
    ): bool {
        if (!$classModule->canBeRemoved()) {
            return false;
        }

        DB::transaction(function () use ($training, $classModule) {
            // Delete progress rows for this module
            MenteeModuleProgress::where('class_module_id', $classModule->id)->delete();

            // Release the usage record
            MentorshipModuleUsage::where('mentorship_id', $training->id)
                    ->where('module_id', $classModule->program_module_id)
                    ->delete();

            // Delete the class module (cascades to sessions via FK)
            $classModule->delete();
        });

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auto-roster helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create MenteeModuleProgress rows for all given participants on a module.
     *
     * Called:
     *   A) When a module is added to an already-active class (new module)
     *   B) From MenteeEnrollmentController when a mentee joins (all existing modules)
     *   C) From ClassModule::start() as a sync guard (ensureMenteeProgressRecords)
     *
     * Uses firstOrCreate so it is safe to call multiple times (idempotent).
     */
    public function cascadeRosterToModule(
            ClassModule $classModule,
            Collection $participants,
            array $completedByUser = []
    ): int {
        $created = 0;

        // Determine what status a progress row should start at for this module
        $moduleStatus = match ($classModule->status) {
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            default => 'not_started',
        };

        foreach ($participants as $participant) {
            $completedModuleIds = $completedByUser[$participant->id] ?? $this->getCompletedModuleIds($participant->user_id);

            $isExempted = in_array($classModule->program_module_id, $completedModuleIds);

            [$record, $wasCreated] = [
                MenteeModuleProgress::firstOrCreate(
                        [
                            'class_participant_id' => $participant->id,
                            'class_module_id' => $classModule->id,
                        ],
                        [
                            'status' => $isExempted ? 'exempted' : $moduleStatus,
                            'started_at' => ($isExempted || $moduleStatus === 'in_progress') ? now() : null,
                            'completed_in_previous_class' => $isExempted,
                            'exempted_at' => $isExempted ? now() : null,
                        ]
                ),
                null,
            ];

            // firstOrCreate doesn't return wasCreated directly in all versions
            if (!$record->wasRecentlyCreated) {
                continue;
            }

            $created++;
        }

        return $created;
    }

    /**
     * Cascade ALL existing modules in a class to a single new participant.
     *
     * Called from MenteeEnrollmentController when a mentee enrolls (including
     * late joiners after class has started).
     */
    public function cascadeAllModulesToParticipant(
            MentorshipClass $class,
            ClassParticipant $participant
    ): int {
        $class->loadMissing('classModules');

        $completedModuleIds = $this->getCompletedModuleIds($participant->user_id);
        $created = 0;

        foreach ($class->classModules as $classModule) {
            $isExempted = in_array($classModule->program_module_id, $completedModuleIds);

            $moduleStatus = match ($classModule->status) {
                'in_progress' => 'in_progress',
                'completed' => 'completed',
                default => 'not_started',
            };

            $record = MenteeModuleProgress::firstOrCreate(
                    [
                        'class_participant_id' => $participant->id,
                        'class_module_id' => $classModule->id,
                    ],
                    [
                        'status' => $isExempted ? 'exempted' : $moduleStatus,
                        'started_at' => ($isExempted || $moduleStatus === 'in_progress') ? now() : null,
                        'completed_in_previous_class' => $isExempted,
                        'exempted_at' => $isExempted ? now() : null,
                    ]
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get program_module_ids a user has already completed in any previous class.
     * Used for exemption detection on enrollment.
     *
     * Uses two independent sources and merges them — whichever confirms presence
     * first wins. This makes exemption robust even if one source is incomplete.
     *
     * Source 1 — MenteeModuleProgress (status = completed|exempted)
     *   Set by ClassModule::complete() for mentees who confirmed attendance.
     *   Covers normal flow and prior exemptions.
     *
     * Source 2 — ClassAttendance (direct attendance record)
     *   Stronger proof: the mentee physically clicked the attendance link.
     *   Acts as fallback if progress records were not finalized for any reason
     *   (e.g. mentor ended class without completing all modules).
     *
     * Both sources match on program_module_id — stable identity across all
     * mentorships and classes on the platform.
     */
    public function getCompletedModuleIds(int $userId): array {
        // Source 1: progress records marked completed or exempted
        $fromProgress = DB::table('class_participants')
                ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
                ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
                ->where('class_participants.user_id', $userId)
                ->whereIn('mentee_module_progress.status', ['completed', 'exempted'])
                ->pluck('class_modules.program_module_id');

        // Source 2: direct ClassAttendance records (mentee confirmed presence via link)
        // Requires class_module_id column — added by migration:
        //   add_class_module_id_to_class_attendances
        $fromAttendance = DB::table('class_attendances')
                ->join('class_modules', 'class_attendances.class_module_id', '=', 'class_modules.id')
                ->where('class_attendances.user_id', $userId)
                ->whereNotNull('class_attendances.class_module_id')
                ->pluck('class_modules.program_module_id');

        return $fromProgress
                        ->merge($fromAttendance)
                        ->unique()
                        ->values()
                        ->toArray();
    }
}
