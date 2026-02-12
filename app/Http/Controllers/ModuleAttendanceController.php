<?php

namespace App\Http\Controllers;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleAttendanceController extends Controller {

    /**
     * Handle module attendance link access
     */
    private function hasCompletedModuleBefore($user, $programModuleId): bool {
        return \Illuminate\Support\Facades\DB::table('class_participants')
                        ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
                        ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
                        ->where('class_participants.user_id', $user->id)
                        ->where('class_modules.program_module_id', $programModuleId)
                        ->where('mentee_module_progress.status', 'completed')
                        ->exists();
    }

    public function attend(Request $request, string $token) {
        // Find module by attendance token
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
     * Process attendance submission
     */
    public function processAttendance(Request $request, string $token) {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $classModule = ClassModule::where('attendance_token', $token)
                ->where('attendance_link_active', true)
                ->firstOrFail();

        // Find user by phone
        $user = \App\Models\User::where('phone', $request->phone)->first();

        if (!$user) {
            return back()->withErrors([
                        'phone' => 'Phone number not found. Please contact your mentor.',
            ]);
        }

        return $this->markAttendance($user, $classModule);
    }

    /**
     * Mark user as attended for this module
     */
    private function markAttendance($user, ClassModule $classModule) {
        // NEW: Add this check at the beginning
        if ($this->hasCompletedModuleBefore($user, $classModule->program_module_id)) {
            return view('mentee.attendance-already-completed', [
                'module' => $classModule,
                'class' => $classModule->class,
                'user' => $user,
                'message' => 'You have already completed this module in a previous class.',
            ]);
        }

        // ... existing code to find participant and progress ...

        $participant = ClassParticipant::where('mentorship_class_id', $classModule->mentorship_class_id)
                ->where('user_id', $user->id)
                ->first();

        if (!$participant) {
            return back()->withErrors(['error' => 'You are not enrolled in this class.']);
        }

        $progress = MenteeModuleProgress::firstOrCreate(
                [
                    'class_participant_id' => $participant->id,
                    'class_module_id' => $classModule->id,
                ],
                [
                    'status' => 'not_started',
                ]
        );

        // CHANGE: Update to mark as 'completed' instead of 'in_progress'
        $progress->update([
            'status' => 'completed', // <-- CHANGED from 'in_progress'
            'started_at' => $progress->started_at ?? now(),
            'completed_at' => now(), // <-- ADDED
        ]);

        // ... rest of existing code ...

        return view('mentee.attendance-confirmed', [
            'module' => $classModule,
            'class' => $classModule->class,
            'user' => $user,
            'progress' => $progress,
        ]);
    }
}
