<?php

namespace App\Http\Controllers;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ModuleAttendanceController extends Controller {

    public function __construct(
            private AttendanceService $attendanceService
    ) {
        
    }

    /**
     * Handle module attendance link access.
     */
    public function attend(Request $request, string $token) {
        $classModule = ClassModule::where('attendance_token', $token)
                ->where('attendance_link_active', true)
                ->with(['programModule', 'mentorshipClass.training'])
                ->firstOrFail();

        // Check if module is accepting attendance
        if ($classModule->status === 'completed') {
            return view('mentee.attendance-closed', [
                'module' => $classModule,
                'message' => 'This module has been completed. Attendance is no longer being tracked.',
            ]);
        }

        // If user is logged in
        if (Auth::check()) {
            return $this->markAttendance(Auth::user(), $classModule);
        }

        // Show attendance form for guest users
        return view('mentee.attendance-form', [
            'module' => $classModule,
            'token' => $token,
        ]);
    }

    /**
     * Process attendance submission from guest form.
     */
    public function processAttendance(Request $request, string $token) {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $classModule = ClassModule::where('attendance_token', $token)
                ->where('attendance_link_active', true)
                ->with(['programModule', 'mentorshipClass.training'])
                ->firstOrFail();

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return back()->withErrors([
                        'phone' => 'Phone number not found. Please contact your mentor.',
            ]);
        }

        return $this->markAttendance($user, $classModule);
    }

    /**
     * Mark user as attended for this module using AttendanceService.
     */
    private function markAttendance(User $user, ClassModule $classModule) {
        // Check if user has completed this module in a previous class (exempted)
        if ($this->hasCompletedModuleBefore($user, $classModule->program_module_id)) {
            return view('mentee.attendance-already-completed', [
                'module' => $classModule,
                'class' => $classModule->mentorshipClass,
                'user' => $user,
                'message' => 'You have already completed this module in a previous class.',
            ]);
        }

        // Find participant enrollment
        $participant = ClassParticipant::where('mentorship_class_id', $classModule->mentorship_class_id)
                ->where('user_id', $user->id)
                ->first();

        if (!$participant) {
            return back()->withErrors(['error' => 'You are not enrolled in this class.']);
        }

        // Check if attendance already recorded (immutability rule)
        $alreadyAttended = $this->attendanceService->hasAttendance(
                classId: $classModule->mentorship_class_id,
                sessionId: $classModule->id, // using module ID as session context
                userId: $user->id
        );

        if ($alreadyAttended) {
            $progress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                    ->where('class_module_id', $classModule->id)
                    ->first();

            return view('mentee.attendance-confirmed', [
                'module' => $classModule,
                'class' => $classModule->mentorshipClass,
                'user' => $user,
                'progress' => $progress,
                'already_marked' => true,
            ]);
        }

        // Record attendance via AttendanceService (immutable record)
        $this->attendanceService->markAttendance(
                classId: $classModule->mentorship_class_id,
                sessionId: $classModule->id,
                userId: $user->id,
                markedBy: $user->id,
                source: 'auto' // via attendance link
        );

        // Update module progress
        $progress = MenteeModuleProgress::firstOrCreate(
                [
                    'class_participant_id' => $participant->id,
                    'class_module_id' => $classModule->id,
                ],
                [
                    'status' => 'not_started',
                ]
        );

        $progress->update([
            'status' => 'completed',
            'started_at' => $progress->started_at ?? now(),
            'completed_at' => now(),
        ]);

        return view('mentee.attendance-confirmed', [
            'module' => $classModule,
            'class' => $classModule->mentorshipClass,
            'user' => $user,
            'progress' => $progress,
            'already_marked' => false,
        ]);
    }

    /**
     * Check if user has completed this module in any previous class.
     */
    private function hasCompletedModuleBefore(User $user, int $programModuleId): bool {
        return DB::table('class_participants')
                        ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
                        ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
                        ->where('class_participants.user_id', $user->id)
                        ->where('class_modules.program_module_id', $programModuleId)
                        ->where('mentee_module_progress.status', 'completed')
                        ->exists();
    }
}
