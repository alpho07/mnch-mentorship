<?php

namespace App\Http\Controllers;

use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use Illuminate\Support\Facades\Auth;
use Spatie\Browsershot\Browsershot;

class ClassReportController extends Controller
{
    // ── Auth guard ────────────────────────────────────────────────────────────
    private function authorizeClass(MentorshipClass $class): void
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole(['super_admin', 'admin', 'division', 'national_mentor']);

        if (! $isAdmin) {
            abort_unless(
                $class->training->mentor_id === $user->id ||
                $class->training->isCoMentor($user->id),
                403,
                'You do not have access to this class report.'
            );
        }
    }

    // ── Shared data builder ───────────────────────────────────────────────────
    private function buildReportData(MentorshipClass $class): array
    {
        $class->load([
            'training.program',
            'training.facility',
            'training.mentor',
            'training.coMentors.user',
            'classModules.programModule',
            'participants.user.cadre',
        ]);

        $modules = $class->classModules->sortBy('order_sequence');

        $mentees = $class->participants
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->sortBy(fn ($p) => $p->user->name ?? '')
            ->map(function (ClassParticipant $participant) use ($modules) {
                $progress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                    ->whereIn('class_module_id', $modules->pluck('id'))
                    ->get()
                    ->keyBy('class_module_id');

                $attended = $progress->whereIn('status', ['in_progress', 'completed', 'exempted'])->count();
                $total = $modules->count();
                $rate = $total > 0 ? round(($attended / $total) * 100) : 0;

                return [
                    'participant' => $participant,
                    'user' => $participant->user,
                    'progress' => $progress,
                    'attended' => $attended,
                    'completed' => $progress->where('status', 'completed')->count(),
                    'total_modules' => $total,
                    'attendance_pct' => $rate,
                    'class_complete' => $participant->status === 'completed',
                ];
            });

        $totalEnrolled = $mentees->count();
        $totalCompleted = $mentees->where('class_complete', true)->count();
        $avgAttendance = $mentees->avg('attendance_pct');
        $coMentors = $class->training->coMentors
            ->where('status', 'accepted')
            ->pluck('user.name')
            ->filter()
            ->values();

        return compact('class', 'modules', 'mentees', 'totalEnrolled', 'totalCompleted', 'avgAttendance', 'coMentors');
    }

    public function certificateHtml(MentorshipClass $class, ClassParticipant $participant)
    {
        $this->authorizeClass($class);
        abort_unless($participant->mentorship_class_id === $class->id, 404);

        $class->load(['training.program', 'training.facility', 'training.mentor']);
        $participant->load('user');

        abort_unless($participant->status === 'completed', 403, 'Mentee has not completed the class.');

        $modules = $class->classModules()->with('programModule')->orderBy('order_sequence')->get();

        return view('certificates.completion', compact('class', 'participant', 'modules'));
    }

    // ── HTML Report (web view) ────────────────────────────────────────────────
    public function html(MentorshipClass $class)
    {
        $this->authorizeClass($class);
        $data = $this->buildReportData($class);

        return view('reports.class-report', $data);
    }

    // ── PDF Report (Browsershot → Chrome print) ───────────────────────────────
    public function pdf(MentorshipClass $class)
    {
        $this->authorizeClass($class);
        $data = $this->buildReportData($class);
        $html = view('reports.class-report', array_merge($data, ['isPdf' => true]))->render();

        $filename = 'MNCH-Report-'.str($class->name)->slug().'-'.now()->format('Y-m-d').'.pdf';

        $pdf = $this->makeBrowsershot($html)
            ->landscape()
            ->format('A4')
            ->margins(8, 8, 8, 8)
            ->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function certificate(MentorshipClass $class, ClassParticipant $participant)
    {
        $this->authorizeClass($class);
        abort_unless($participant->mentorship_class_id === $class->id, 404);
        $class->load(['training.program', 'training.facility', 'training.mentor']);
        $participant->load('user');
        abort_unless($participant->status === 'completed', 403, 'Mentee has not completed the class.');

        $modules = $class->classModules()->with('programModule')->orderBy('order_sequence')->get();
        $html = view('certificates.completion', compact('class', 'participant', 'modules'))->render();

        $name = str($participant->user->name ?? 'mentee')->slug();
        $filename = "MNCH-Certificate-{$name}-".now()->format('Y-m-d').'.pdf';

        $pdf = $this->makeBrowsershot($html)
            ->landscape()
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function makeBrowsershot(string $html): \Spatie\Browsershot\Browsershot
    {
        $shot = \Spatie\Browsershot\Browsershot::html($html)
            ->setChromePath(config('browsershot.chrome_path', '/usr/bin/chromium-browser'))
            ->addChromiumArguments([
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
            ])
            ->showBackground()
            ->waitUntilNetworkIdle();

        return $shot;
    }
}
