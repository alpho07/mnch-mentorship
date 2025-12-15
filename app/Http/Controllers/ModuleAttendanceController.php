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
        // Get or create class participant
        $participant = ClassParticipant::firstOrCreate(
                [
                    'mentorship_class_id' => $classModule->mentorship_class_id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'enrolled',
                    'enrolled_at' => now(),
                ]
        );

        // Get or create module progress
        $progress = MenteeModuleProgress::firstOrCreate(
                [
                    'class_participant_id' => $participant->id,
                    'class_module_id' => $classModule->id,
                ],
                [
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]
        );

        // Mark as in progress if not started
        if ($progress->status === 'not_started') {
            $progress->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }

        return view('mentee.attendance-confirmed', [
            'module' => $classModule,
            'user' => $user,
            'progress' => $progress,
        ]);
    }
}
