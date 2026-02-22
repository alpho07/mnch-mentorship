<?php

namespace App\Filament\Resources\MentorshipTrainingResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class MenteeDashboard extends Page
{
    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view     = 'filament.pages.mentee-dashboard';
    protected static bool   $shouldRegisterNavigation = false;

    public array $enrollments      = [];
    public array $confirmedModules = [];
    public array $summaryStats     = [];

    public function mount(): void
    {
        $this->loadDashboard();
    }

    public function loadDashboard(): void
    {
        $user = auth()->user();

        $participants = ClassParticipant::with([
            'mentorshipClass.training.facility',
            'mentorshipClass.classModules'           => fn ($q) => $q->orderBy('order_sequence'),
            'mentorshipClass.classModules.programModule',
            'mentorshipClass.classModules.sessions'  => fn ($q) => $q->orderBy('session_number'),
            'moduleProgress',
        ])
            ->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $classIds = $participants->pluck('mentorship_class_id');

        $this->confirmedModules = ClassAttendance::where('user_id', $user->id)
            ->whereIn('class_id', $classIds)
            ->whereNotNull('class_module_id')
            ->pluck('class_module_id')
            ->flip()
            ->toArray();

        $confirmed = $this->confirmedModules;

        $this->enrollments = $participants->map(function (ClassParticipant $p) use ($confirmed) {
            $class    = $p->mentorshipClass;
            $training = $class?->training;

            $modules = ($class?->classModules ?? collect())->map(function (ClassModule $m) use ($p, $confirmed) {
                $prog = $p->moduleProgress->firstWhere('class_module_id', $m->id);

                $sessions = $m->sessions->map(fn ($s) => [
                    'session_number'   => $s->session_number,
                    'title'            => $s->title,
                    'description'      => $s->description,
                    'duration_minutes' => $s->duration_minutes,
                    'scheduled_date'   => $s->scheduled_date ? Carbon::parse($s->scheduled_date)->format('d M Y') : null,
                    'scheduled_time'   => $s->scheduled_time ? Carbon::parse($s->scheduled_time)->format('H:i') : null,
                    'location'         => $s->location,
                    'status'           => $s->status,
                ])->values()->toArray();

                return [
                    'id'                => $m->id,
                    'name'              => $m->programModule?->name ?? 'Unnamed Module',
                    'description'       => $m->programModule?->description,
                    'status'            => $m->status,
                    'attendance_open'   => $m->attendance_link_active && $m->status === 'in_progress',
                    'already_confirmed' => isset($confirmed[$m->id]),
                    'sessions'          => $sessions,
                    'progress_status'   => $prog?->status ?? 'not_started',
                    'completed_at'      => $prog?->completed_at ? Carbon::parse($prog->completed_at)->format('d M Y') : null,
                    'started_at'        => $m->started_at ? Carbon::parse($m->started_at)->format('d M Y') : null,
                    'recommendation'    => $prog?->mentor_recommendation,
                    'rec_written_at'    => $prog?->recommendation_written_at ? Carbon::parse($prog->recommendation_written_at)->format('d M Y') : null,
                ];
            })->values()->toArray();

            $done    = collect($modules)->where('progress_status', 'completed')->count();
            $inProg  = collect($modules)->where('progress_status', 'in_progress')->count();
            $total   = count($modules);
            $pct     = $total > 0 ? round(($done / $total) * 100) : 0;
            $pending = collect($modules)->where('attendance_open', true)->where('already_confirmed', false)->count();
            $recs    = collect($modules)->filter(fn ($m) => !empty($m['recommendation']))->count();

            return [
                'participant_id'      => $p->id,
                'class_id'            => $class?->id,
                'class_name'          => $class?->name ?? 'Unnamed Class',
                'class_status'        => $class?->status,
                'training_name'       => $training?->title ?? 'Unnamed Mentorship',
                'facility_name'       => $training?->facility?->name ?? '',
                'progress_pct'        => $pct,
                'completed_modules'   => $done,
                'in_progress_modules' => $inProg,
                'total_modules'       => $total,
                'pending_attendance'  => $pending,
                'recommendations'     => $recs,
                'enrolled_at'         => $p->enrolled_at ? Carbon::parse($p->enrolled_at)->format('d M Y') : null,
                'modules'             => $modules,
            ];
        })->values()->toArray();

        $tMods = array_sum(array_column($this->enrollments, 'total_modules'));
        $cMods = array_sum(array_column($this->enrollments, 'completed_modules'));

        $this->summaryStats = [
            'enrollments'        => count($this->enrollments),
            'total_modules'      => $tMods,
            'completed_modules'  => $cMods,
            'pending_attendance' => array_sum(array_column($this->enrollments, 'pending_attendance')),
            'recommendations'    => array_sum(array_column($this->enrollments, 'recommendations')),
            'overall_pct'        => $tMods > 0 ? round(($cMods / $tMods) * 100) : 0,
        ];
    }

    public function confirmAttendance(int $classModuleId): void
    {
        $module = ClassModule::find($classModuleId);

        if (!$module) {
            Notification::make()->danger()->title('Module not found')->send();
            return;
        }

        $result = app(AttendanceService::class)->confirmModuleAttendance(auth()->user(), $module);

        if ($result['success']) {
            $this->confirmedModules[$classModuleId] = true;
            Notification::make()
                ->success()
                ->title('Attendance Confirmed')
                ->body('You are marked present for "' . $module->programModule?->name . '".')
                ->send();
            $this->loadDashboard();
        } else {
            Notification::make()->warning()->title($result['message'])->send();
        }
    }
}