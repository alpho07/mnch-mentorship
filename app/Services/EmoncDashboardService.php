<?php

namespace App\Services;

use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\MentorshipCoMentor;
use App\Models\Training;
use App\Models\User;

class EmoncDashboardService
{
    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    public function build(User $user): array
    {
        $trainingIds = $this->trainingIdsFor($user);

        $trainings = Training::whereIn('id', $trainingIds)
            ->where('type', 'facility_mentorship')
            ->with(['facility.subcounty.county', 'program', 'mentor'])
            ->get();

        $emoncTrainings = $trainings->filter(fn ($t) => $this->isEmonc($t));
        $emoncTrainingIds = $emoncTrainings->pluck('id');

        $classes = MentorshipClass::whereIn('training_id', $emoncTrainingIds)
            ->with([
                'classModules.programModule.parent',
                'classModules.programModule.activities',
                'training.facility.subcounty.county',
            ])
            ->get();

        $classIds = $classes->pluck('id');
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));

        $participants = ClassParticipant::with('user')
            ->whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $progress = MenteeModuleProgress::whereIn('class_module_id', $classModuleIds)
            ->get()
            ->keyBy(fn ($p) => "{$p->class_participant_id}:{$p->class_module_id}");

        $activityParticipants = ClassModuleActivityParticipant::whereIn('class_module_id', $classModuleIds)
            ->get()
            ->groupBy(fn ($ap) => "{$ap->class_participant_id}:{$ap->class_module_id}");

        $kpis = $this->computeKpis($classes, $participants, $progress);
        $pendingActions = $this->computePendingActions($classes, $participants, $progress);
        $completionMatrix = $this->buildCompletionMatrix($classes, $participants, $progress, $activityParticipants);
        $chartData = $this->buildChartData($classes, $participants, $progress);

        return compact('kpis', 'pendingActions', 'completionMatrix', 'chartData');
    }

    private function trainingIdsFor(User $user): array
    {
        if ($user->hasRole(self::SENIOR_ROLES)) {
            return Training::where('type', 'facility_mentorship')->pluck('id')->toArray();
        }

        $asLead = Training::where('mentor_id', $user->id)
            ->where('type', 'facility_mentorship')
            ->pluck('id');

        $asCo = MentorshipCoMentor::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('training_id');

        return $asLead->merge($asCo)->unique()->values()->toArray();
    }

    private function isEmonc(Training $training): bool
    {
        $name = strtolower($training->program?->name ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }

    private function computeKpis($classes, $participants, $progress): array
    {
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));

        $totalMentees = $participants->pluck('user_id')->unique()->count();
        $activeClasses = $classes->where('status', 'active')->count();
        $completedClasses = $classes->where('status', 'completed')->count();
        $totalModules = $classModuleIds->count();
        $completedModules = $classes->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());

        $videoPending = $progress->where('video_review_status', 'pending')->count();
        $mentorPending = $participants->whereNull('mentor_approved_at')->where('status', '!=', 'dropped')->count();
        $drmhPending = $participants->whereNotNull('mentor_approved_at')->whereNull('head_drmh_approved_at')->count();
        $certified = $participants->whereNotNull('head_drmh_approved_at')->count();

        return [
            'active_mentees' => $totalMentees,
            'active_classes' => $activeClasses,
            'completed_classes' => $completedClasses,
            'total_modules' => $totalModules,
            'completed_modules' => $completedModules,
            'pending_video_reviews' => $videoPending,
            'pending_mentor_approvals' => $mentorPending,
            'pending_drmh_approvals' => $drmhPending,
            'certificates_issued' => $certified,
        ];
    }

    private function computePendingActions($classes, $participants, $progress): array
    {
        return [
            [
                'label' => 'Video reviews',
                'count' => $progress->where('video_review_status', 'pending')->count(),
                'url' => '/admin',
                'color' => 'amber',
            ],
            [
                'label' => 'Mentor approvals',
                'count' => $participants->whereNull('mentor_approved_at')->where('status', '!=', 'dropped')->count(),
                'url' => '/admin',
                'color' => 'blue',
            ],
            [
                'label' => 'Head DRMH certifications',
                'count' => $participants->whereNotNull('mentor_approved_at')->whereNull('head_drmh_approved_at')->count(),
                'url' => '/admin',
                'color' => 'violet',
            ],
        ];
    }

    private function buildCompletionMatrix($classes, $participants, $progress, $activityParticipants): array
    {
        $matrix = [];

        foreach ($classes as $class) {
            foreach ($class->classModules as $classModule) {
                $programModule = $classModule->programModule;
                $moduleName = $programModule?->name ?? 'Module';
                $parentName = $programModule?->parent?->name;
                $activities = $programModule?->activities ?? collect();

                foreach ($participants->where('mentorship_class_id', $class->id) as $participant) {
                    $key = "{$participant->id}:{$classModule->id}";
                    $prog = $progress->get($key);
                    $activityStatus = $this->activityStatusFor($activities, $activityParticipants->get($key, collect()));

                    $matrix[] = [
                        'class_id' => $class->id,
                        'class_name' => $class->name,
                        'facility_name' => $class->training->facility?->name ?? '—',
                        'county_name' => $class->training->facility?->subcounty?->county?->name ?? '—',
                        'participant_id' => $participant->id,
                        'user_id' => $participant->user_id,
                        'mentee_name' => $participant->user?->name ?? 'Unknown',
                        'module_id' => $classModule->id,
                        'module_name' => $moduleName,
                        'parent_module_name' => $parentName,
                        'activities' => $activityStatus,
                        'module_progress' => $prog?->status ?? 'not_started',
                        'video_review' => $prog?->video_review_status ?? 'not_submitted',
                        'mentor_approved' => ! empty($participant->mentor_approved_at),
                        'head_drmh_approved' => ! empty($participant->head_drmh_approved_at),
                        'certificate_ready' => $participant->isCertified(),
                        'blocked_reasons' => $this->blockedReasons($participant, $prog, $activityStatus),
                    ];
                }
            }
        }

        return $matrix;
    }

    private function activityStatusFor($activities, $activityParticipants): array
    {
        $status = [];
        foreach ($activities as $activity) {
            $ap = $activityParticipants->firstWhere('activity_id', $activity->id);
            $status[] = [
                'name' => $activity->name,
                'status' => $ap?->status ?? 'not_enrolled',
            ];
        }

        return $status;
    }

    private function blockedReasons(ClassParticipant $participant, ?MenteeModuleProgress $progress, array $activityStatus): array
    {
        $reasons = [];
        foreach ($activityStatus as $a) {
            if ($a['status'] !== 'completed') {
                $reasons[] = "{$a['name']} not completed";
            }
        }
        if ($progress && $progress->video_review_status !== 'passed') {
            $reasons[] = 'Hands-on video not passed';
        }
        if (empty($participant->mentor_approved_at)) {
            $reasons[] = 'Pending mentor approval';
        }
        if (empty($participant->head_drmh_approved_at)) {
            $reasons[] = 'Pending Head DRMH certification';
        }

        return $reasons;
    }

    private function buildChartData($classes, $participants, $progress): array
    {
        return [
            'completion_distribution' => [
                ['label' => 'Completed', 'value' => $progress->where('status', 'completed')->count()],
                ['label' => 'In Progress', 'value' => $progress->where('status', 'in_progress')->count()],
                ['label' => 'Not Started', 'value' => $progress->where('status', 'not_started')->count()],
            ],
        ];
    }
}
