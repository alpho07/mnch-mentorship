<?php

namespace App\Services;

use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ProgramModule;
use App\Models\MentorshipModuleUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Enforces module usage rules at TWO levels:
 *
 * LEVEL 1 — Within this mentorship:
 *   Module used in class A → cannot be used in class B of the same mentorship.
 *   These modules are HIDDEN from the selection UI entirely.
 *
 * LEVEL 2 — Across mentorships at the same facility:
 *   Module was completed in a DIFFERENT mentorship at the same facility.
 *   These modules are SHOWN but DISABLED with a reason explaining where/when it was completed.
 *
 * POLICY: Option B — removal keeps usage locked historically within the mentorship.
 */
class ModuleUsageService
{
    /**
     * Get program IDs associated with a training.
     * Checks both direct program_id and the training_programs pivot.
     */
    private function getTrainingProgramIds(Training $mentorship): array
    {
        $ids = [];

        // Direct program_id on the training
        if ($mentorship->program_id) {
            $ids[] = $mentorship->program_id;
        }

        // Many-to-many via training_programs pivot
        $pivotIds = DB::table('training_programs')
            ->where('training_id', $mentorship->id)
            ->pluck('program_id')
            ->toArray();

        return array_unique(array_merge($ids, $pivotIds));
    }

    /**
     * Get structured module availability for the Add Modules UI.
     *
     * Returns a collection of objects with:
     *   - module: ProgramModule model
     *   - available: bool (can it be selected?)
     *   - disabled_reason: string|null (why it's disabled)
     *
     * Level 1 modules (used in this mentorship) are excluded entirely.
     * Level 2 modules (completed at same facility) are included but marked disabled.
     */
    public function getModulesWithAvailability(Training $mentorship, MentorshipClass $class): Collection
    {
        // Level 1: IDs used within THIS mentorship (hide completely)
        $usedInThisMentorship = MentorshipModuleUsage::where('mentorship_id', $mentorship->id)
            ->pluck('module_id')
            ->toArray();

        // Also IDs already in this class
        $inThisClass = $class->classModules()
            ->pluck('program_module_id')
            ->toArray();

        $hideIds = array_unique(array_merge($usedInThisMentorship, $inThisClass));

        // All program modules for this training's program(s) — excluding Level 1
        $programIds = $this->getTrainingProgramIds($mentorship);

        $allModules = ProgramModule::whereIn('program_id', $programIds)
            ->when(! empty($hideIds), fn ($q) => $q->whereNotIn('id', $hideIds))
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->get();

        // Level 2: Modules completed in OTHER mentorships at the same facility
        $completedElsewhere = $this->getModulesCompletedAtFacility($mentorship);

        return $allModules->map(function (ProgramModule $module) use ($completedElsewhere) {
            $completionInfo = $completedElsewhere->get($module->id);

            return (object) [
                'module' => $module,
                'available' => $completionInfo === null,
                'disabled_reason' => $completionInfo,
            ];
        });
    }

    /**
     * Get modules that have been completed in OTHER mentorships at the same facility.
     *
     * Returns: Collection keyed by program_module_id => "Completed in {mentorship name} ({date})"
     */
    private function getModulesCompletedAtFacility(Training $mentorship): Collection
    {
        $facilityId = $mentorship->facility_id;

        if (! $facilityId) {
            return collect();
        }

        // Find all OTHER mentorships at the same facility
        $otherMentorshipIds = Training::where('facility_id', $facilityId)
            ->where('type', 'facility_mentorship')
            ->where('id', '!=', $mentorship->id)
            ->pluck('id')
            ->toArray();

        if (empty($otherMentorshipIds)) {
            return collect();
        }

        // Find modules that were COMPLETED in those mentorships
        // A module is "completed" if its class_module status = completed
        return DB::table('class_modules')
            ->join('mentorship_classes', 'class_modules.mentorship_class_id', '=', 'mentorship_classes.id')
            ->join('trainings', 'mentorship_classes.training_id', '=', 'trainings.id')
            ->whereIn('mentorship_classes.training_id', $otherMentorshipIds)
            ->where('class_modules.status', 'completed')
            ->select([
                'class_modules.program_module_id',
                'trainings.identifier as mentorship_identifier',
                'trainings.id as training_id',
                'mentorship_classes.name as class_name',
                'class_modules.completed_at',
            ])
            ->get()
            ->groupBy('program_module_id')
            ->map(function ($records) {
                // Take the most recent completion
                $latest = $records->sortByDesc('completed_at')->first();

                $identifier = $latest->mentorship_identifier ?? "Mentorship #{$latest->training_id}";
                $className = $latest->class_name;
                $date = $latest->completed_at
                    ? \Carbon\Carbon::parse($latest->completed_at)->format('M j, Y')
                    : 'previously';

                return "Already completed in {$identifier} — {$className} ({$date})";
            });
    }

    /**
     * Get only the selectable (available) modules for a class.
     * Used by service internals and for count displays.
     */
    public function getAvailableModules(Training $mentorship, MentorshipClass $class): Collection
    {
        return $this->getModulesWithAvailability($mentorship, $class)
            ->filter(fn ($item) => $item->available)
            ->map(fn ($item) => $item->module);
    }

    /**
     * Assign selected modules to a class and record usage atomically.
     *
     * DEFENSIVE: Double-checks usage before insert. Also blocks Level 2 modules.
     *
     * @return int Number of modules assigned
     */
    public function assignModulesToClass(
        Training $mentorship,
        MentorshipClass $class,
        array $programModuleIds
    ): int {
        $assigned = 0;

        // Pre-filter: get the full availability map to block Level 2 modules
        $availability = $this->getModulesWithAvailability($mentorship, $class)
            ->keyBy(fn ($item) => $item->module->id);

        DB::transaction(function () use ($mentorship, $class, $programModuleIds, &$assigned, $availability) {
            $maxSequence = $class->classModules()->max('order_sequence') ?? 0;

            foreach ($programModuleIds as $moduleId) {
                // Block if Level 2 disabled
                $info = $availability->get($moduleId);
                if ($info && ! $info->available) {
                    continue;
                }

                // DEFENSIVE: Check Level 1 usage within transaction (race condition guard)
                $alreadyUsed = MentorshipModuleUsage::where('mentorship_id', $mentorship->id)
                    ->where('module_id', $moduleId)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyUsed) {
                    continue;
                }

                // Create the class_module record
                ClassModule::create([
                    'mentorship_class_id' => $class->id,
                    'program_module_id'   => $moduleId,
                    'status'              => 'not_started',
                    'order_sequence'      => ++$maxSequence,
                ]);

                // Record usage at mentorship level (domain invariant)
                MentorshipModuleUsage::create([
                    'mentorship_id'  => $mentorship->id,
                    'module_id'      => $moduleId,
                    'first_class_id' => $class->id,
                ]);

                $assigned++;
            }
        });

        return $assigned;
    }

    /**
     * Remove a module from a class.
     *
     * OPTION B: Usage record is NOT deleted. Module remains permanently consumed.
     * Blocked if module has completed sessions.
     */
    public function removeModuleFromClass(
        Training $mentorship,
        MentorshipClass $class,
        ClassModule $classModule
    ): bool {
        $hasCompletedSessions = $classModule->sessions()
            ->where('status', 'completed')
            ->exists();

        if ($hasCompletedSessions) {
            return false;
        }

        DB::transaction(function () use ($classModule) {
            $classModule->menteeProgress()->delete();
            $classModule->sessions()->delete();
            $classModule->delete();
            // Usage record NOT deleted (Option B)
        });

        return true;
    }

    /**
     * Get modules already used across all classes in this mentorship.
     */
    public function getUsedModules(Training $mentorship): Collection
    {
        return MentorshipModuleUsage::where('mentorship_id', $mentorship->id)
            ->with(['module', 'firstClass'])
            ->get();
    }

    /**
     * Check if a specific module has been used in this mentorship.
     */
    public function isModuleUsed(Training $mentorship, int $moduleId): bool
    {
        return MentorshipModuleUsage::where('mentorship_id', $mentorship->id)
            ->where('module_id', $moduleId)
            ->exists();
    }

    /**
     * Backfill usage records from existing class_modules data.
     * Run ONCE after migration.
     */
    public function backfillUsageRecords(Training $mentorship): int
    {
        $backfilled = 0;

        $classes = $mentorship->mentorshipClasses()
            ->with('classModules')
            ->orderBy('created_at')
            ->get();

        DB::transaction(function () use ($mentorship, $classes, &$backfilled) {
            foreach ($classes as $class) {
                foreach ($class->classModules as $classModule) {
                    $exists = MentorshipModuleUsage::where('mentorship_id', $mentorship->id)
                        ->where('module_id', $classModule->program_module_id)
                        ->exists();

                    if (! $exists) {
                        MentorshipModuleUsage::create([
                            'mentorship_id'  => $mentorship->id,
                            'module_id'      => $classModule->program_module_id,
                            'first_class_id' => $class->id,
                        ]);
                        $backfilled++;
                    }
                }
            }
        });

        return $backfilled;
    }
}