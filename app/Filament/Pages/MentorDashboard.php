<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\MentorshipCoMentor;
use App\Models\Training;
use Filament\Pages\Page;

class MentorDashboard extends Page
{
    protected static string  $view            = 'filament.pages.mentor-dashboard';
    protected static ?string $slug            = 'mentor-dashboard';
    protected static ?string $navigationGroup = 'Dashboards';
    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Mentor Dashboard';
    protected static ?int    $navigationSort  = 2;

    private const MENTOR_ROLES = [
        'facility_mentor', 'facility_mentor_lead',
        'county_mentor_lead', 'subcounty_mentor_lead',
        'spoke_mentor', 'spoke_mentor_lead',
        'national_mentor_lead', 'national_mentor',
    ];

    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    public static function shouldRegisterNavigation(): bool {
        if (!auth()->check()) return false;
        return auth()->user()->hasRole(array_merge(self::MENTOR_ROLES, self::SENIOR_ROLES));
    }

    public static function canAccess(): bool {
        if (!auth()->check()) return false;
        return auth()->user()->hasRole(array_merge(self::MENTOR_ROLES, self::SENIOR_ROLES));
    }

    public static function getNavigationBadge(): ?string {
        if (!auth()->check()) return null;
        $user = auth()->user();
        $count = Training::where('type', 'facility_mentorship')
            ->when(!$user->hasRole(self::SENIOR_ROLES), fn($q) => $q->where('mentor_id', $user->id))
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string {
        return 'primary';
    }

    // ─── Loaded state ────────────────────────────────────────────────────────
    public array $kpis          = [];
    public array $mentorships   = [];   // per-mentorship breakdown
    public array $menteeRoster  = [];   // all mentees + their stats
    public array $activityFeed  = [];   // recent recommendations + confirmations
    public array $insights      = [];   // derived flags for decision-making

    public function mount(): void
    {
        $this->loadDashboard();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core loader
    // ─────────────────────────────────────────────────────────────────────────

    private function loadDashboard(): void
    {
        $userId      = auth()->id();
        $trainingIds = $this->getMyTrainingIds($userId);

        if (empty($trainingIds)) {
            $this->kpis = $this->emptyKpis();
            return;
        }

        // Pull full data in one batch per entity type
        $trainings   = Training::whereIn('id', $trainingIds)
            ->with(['facility', 'program'])
            ->get()
            ->keyBy('id');

        $classes = MentorshipClass::whereIn('training_id', $trainingIds)
            ->with(['classModules.programModule', 'classModules.attendanceRecords'])
            ->get();

        $classIds       = $classes->pluck('id');
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));

        $participants = ClassParticipant::with('user')
            ->whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $progress = MenteeModuleProgress::whereIn('class_module_id', $classModuleIds)
            ->get();

        $attendances = ClassAttendance::whereIn('class_id', $classIds)
            ->whereNotNull('class_module_id')
            ->get();

        // ── KPIs ──────────────────────────────────────────────────────────────
        $totalMentees      = $participants->pluck('user_id')->unique()->count();
        $totalModules      = $classes->sum(fn ($c) => $c->classModules->count());
        $completedModules  = $classes->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());
        $totalEnrollments  = $participants->count();
        $confirmedAtt      = $attendances->count();

        // Attendance rate: confirmations / (modules_in_progress_or_completed * enrolled_per_class)
        $possibleAttendances = 0;
        foreach ($classes as $class) {
            $enrolled       = $participants->where('mentorship_class_id', $class->id)->count();
            $activeModules  = $class->classModules->whereIn('status', ['in_progress', 'completed'])->count();
            $possibleAttendances += $enrolled * $activeModules;
        }
        $attendanceRate = $possibleAttendances > 0
            ? round(($confirmedAtt / $possibleAttendances) * 100, 1)
            : 0;

        // Completion rate: per mentee, how many modules completed vs total
        $progressByParticipant = $progress->groupBy('class_participant_id');
        $completionRates        = [];
        foreach ($participants as $p) {
            $myProgress = $progressByParticipant->get($p->id, collect());
            $total      = $myProgress->count();
            if ($total === 0) continue;
            $done       = $myProgress->where('status', 'completed')->count();
            $completionRates[] = round(($done / $total) * 100);
        }
        $avgCompletion = count($completionRates) > 0
            ? round(array_sum($completionRates) / count($completionRates), 1)
            : 0;

        $activeClasses    = $classes->where('status', 'active')->count();
        $completedClasses = $classes->where('status', 'completed')->count();
        $recCount         = $progress->whereNotNull('mentor_recommendation')->count();

        $this->kpis = [
            'active_mentorships'  => $trainings->count(),
            'active_classes'      => $activeClasses,
            'completed_classes'   => $completedClasses,
            'total_mentees'       => $totalMentees,
            'total_enrollments'   => $totalEnrollments,
            'total_modules'       => $totalModules,
            'completed_modules'   => $completedModules,
            'attendance_rate'     => $attendanceRate,
            'avg_completion'      => $avgCompletion,
            'recommendations'     => $recCount,
            'module_completion_rate' => $totalModules > 0
                ? round(($completedModules / $totalModules) * 100, 1)
                : 0,
        ];

        // ── Per-Mentorship Breakdown ──────────────────────────────────────────
        $this->mentorships = [];
        foreach ($trainingIds as $tid) {
            $training = $trainings->get($tid);
            if (!$training) continue;

            $myClasses       = $classes->where('training_id', $tid);
            $myClassIds      = $myClasses->pluck('id');
            $myParticipants  = $participants->whereIn('mentorship_class_id', $myClassIds->toArray());
            $myModuleIds     = $myClasses->flatMap(fn ($c) => $c->classModules->pluck('id'));
            $myProgress      = $progress->whereIn('class_module_id', $myModuleIds->toArray());
            $myAttendances   = $attendances->whereIn('class_id', $myClassIds->toArray());

            $mModTotal = $myClasses->sum(fn ($c) => $c->classModules->count());
            $mModDone  = $myClasses->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());
            $mMentees  = $myParticipants->pluck('user_id')->unique()->count();
            $mRecs     = $myProgress->whereNotNull('mentor_recommendation')->count();

            // Module progress distribution
            $notStarted  = $myProgress->where('status', 'not_started')->count();
            $inProgress  = $myProgress->where('status', 'in_progress')->count();
            $completed   = $myProgress->where('status', 'completed')->count();
            $total       = $notStarted + $inProgress + $completed;

            $this->mentorships[] = [
                'id'            => $tid,
                'title'         => $training->title ?? 'Unnamed Mentorship',
                'facility'      => $training->facility?->name ?? '—',
                'status'        => $training->status,
                'start_date'    => $training->start_date,
                'end_date'      => $training->end_date,
                'classes_count' => $myClasses->count(),
                'active_classes'=> $myClasses->where('status', 'active')->count(),
                'mentees'       => $mMentees,
                'modules_total' => $mModTotal,
                'modules_done'  => $mModDone,
                'module_pct'    => $mModTotal > 0 ? round(($mModDone / $mModTotal) * 100) : 0,
                'recommendations'=> $mRecs,
                'dist_not_started' => $total > 0 ? round(($notStarted / $total) * 100) : 0,
                'dist_in_progress' => $total > 0 ? round(($inProgress / $total) * 100) : 0,
                'dist_completed'   => $total > 0 ? round(($completed / $total) * 100) : 0,
                'url'           => MentorshipTrainingResource::getUrl('classes', ['record' => $tid]),
            ];
        }

        // ── Mentee Roster ─────────────────────────────────────────────────────
        $this->menteeRoster = [];
        $seenUsers          = [];

        foreach ($participants as $p) {
            $uid = $p->user_id;
            if (isset($seenUsers[$uid])) continue;
            $seenUsers[$uid] = true;

            $myEnrollments    = $participants->where('user_id', $uid);
            $myParticipantIds = $myEnrollments->pluck('id');
            $myProg           = $progress->whereIn('class_participant_id', $myParticipantIds->toArray());
            $myModTotal       = $myProg->count();
            $myModDone        = $myProg->where('status', 'completed')->count();
            $myRecs           = $myProg->whereNotNull('mentor_recommendation')->count();

            $pct = $myModTotal > 0 ? round(($myModDone / $myModTotal) * 100) : 0;

            $this->menteeRoster[] = [
                'user_id'        => $uid,
                'name'           => $p->user?->name ?? 'Unknown',
                'email'          => $p->user?->email ?? '—',
                'cadre'          => $p->user?->cadre?->name ?? '—',
                'facility'       => $p->user?->primary_facility?->name ?? '—',
                'enrollments'    => $myEnrollments->count(),
                'modules_total'  => $myModTotal,
                'modules_done'   => $myModDone,
                'completion_pct' => $pct,
                'recommendations'=> $myRecs,
                'status_flag'    => $pct >= 80 ? 'on_track'
                    : ($pct >= 40 ? 'in_progress' : 'needs_attention'),
            ];
        }

        // Sort: needs attention first, then by completion desc
        usort($this->menteeRoster, fn ($a, $b) =>
            $a['status_flag'] === 'needs_attention' ? -1
            : ($b['status_flag'] === 'needs_attention' ? 1 : $b['completion_pct'] - $a['completion_pct'])
        );

        // ── Activity Feed ─────────────────────────────────────────────────────
        $recentRecs = MenteeModuleProgress::whereIn('class_module_id', $classModuleIds)
            ->whereNotNull('mentor_recommendation')
            ->whereNotNull('recommendation_written_at')
            ->with(['classParticipant.user', 'classModule.programModule'])
            ->orderByDesc('recommendation_written_at')
            ->limit(10)
            ->get();

        $this->activityFeed = $recentRecs->map(fn ($r) => [
            'type'     => 'recommendation',
            'mentee'   => $r->classParticipant?->user?->name ?? '—',
            'module'   => $r->classModule?->programModule?->name ?? '—',
            'excerpt'  => \Illuminate\Support\Str::limit($r->mentor_recommendation, 80),
            'at'       => $r->recommendation_written_at,
        ])->toArray();

        // ── Insights ──────────────────────────────────────────────────────────
        $needsAttentionCount = collect($this->menteeRoster)->where('status_flag', 'needs_attention')->count();
        $this->insights = [
            'mentees_needing_attention' => $needsAttentionCount,
            'low_attendance_classes'    => $classes->filter(function ($c) use ($attendances, $participants) {
                $enrolled  = $participants->where('mentorship_class_id', $c->id)->count();
                $confirmed = $attendances->where('class_id', $c->id)->count();
                if ($enrolled === 0) return false;
                return ($confirmed / $enrolled) < 0.6;
            })->count(),
            'stalled_modules' => $classes->sum(fn ($c) =>
                $c->classModules->where('status', 'not_started')->count()
            ),
            'recs_coverage' => $totalEnrollments > 0
                ? round(($recCount / max($totalEnrollments, 1)) * 100)
                : 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getMyTrainingIds(int $userId): array
    {
        // Senior roles see all live (non-pilot) facility mentorships as a summary view
        if (auth()->user()->hasRole(self::SENIOR_ROLES)) {
            return Training::where('type', 'facility_mentorship')
                ->where('is_pilot', false)
                ->pluck('id')
                ->toArray();
        }

        $asLead = Training::where('mentor_id', $userId)
            ->where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->pluck('id');

        $asCoMentor = MentorshipCoMentor::where('user_id', $userId)
            ->where('status', 'accepted')
            ->pluck('training_id');

        // Filter co-mentor training IDs to also exclude pilots
        $asCoMentor = Training::whereIn('id', $asCoMentor)
            ->where('is_pilot', false)
            ->pluck('id');

        return $asLead->merge($asCoMentor)->unique()->values()->toArray();
    }

    private function emptyKpis(): array
    {
        return [
            'active_mentorships'  => 0, 'active_classes'    => 0,
            'completed_classes'   => 0, 'total_mentees'     => 0,
            'total_enrollments'   => 0, 'total_modules'     => 0,
            'completed_modules'   => 0, 'attendance_rate'   => 0,
            'avg_completion'      => 0, 'recommendations'   => 0,
            'module_completion_rate' => 0,
        ];
    }
}