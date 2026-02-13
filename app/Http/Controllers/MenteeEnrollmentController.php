<?php

namespace App\Http\Controllers;

use App\Models\MentorshipClass;
use App\Models\ClassParticipant;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenteeEnrollmentController extends Controller {

    public function __construct(
            private EnrollmentService $enrollmentService,
            private AttendanceService $attendanceService
    ) {
        
    }

    /**
     * Handle enrollment link access.
     */
    public function enroll(Request $request, string $token) {
        $class = MentorshipClass::where('enrollment_token', $token)
                ->where('enrollment_link_active', true)
                ->with(['training', 'classModules.programModule'])
                ->firstOrFail();

        // Check if class is accepting enrollments
        if (in_array($class->status, ['completed', 'cancelled'])) {
            return view('mentee.enrollment-closed', [
                'class' => $class,
                'message' => 'This class is no longer accepting enrollments.',
            ]);
        }

        // If user is logged in, process immediately
        if (Auth::check()) {
            return $this->processEnrollment(Auth::user(), $class);
        }

        // Show enrollment page for guest users
        return view('mentee.enrollment-form', [
            'class' => $class,
            'token' => $token,
        ]);
    }

    /**
     * Process enrollment submission from guest form.
     */
    public function processEnrollmentSubmission(Request $request, string $token) {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $class = MentorshipClass::where('enrollment_token', $token)
                ->where('enrollment_link_active', true)
                ->with(['training', 'classModules.programModule'])
                ->firstOrFail();

        // Find user by phone
        $user = User::where('email', $request->phone)->first();

        if (!$user) {
            // Create new user if doesn't exist
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'facility_id' => 'required|exists:facilities,id',
            ]);

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'facility_id' => $request->facility_id,
                'password' => bcrypt('temporary123'),
                'status' => 'active',
            ]);
        }

        return $this->processEnrollment($user, $class);
    }

    /**
     * Process actual enrollment using EnrollmentService + AttendanceService.
     */
    private function processEnrollment(User $user, MentorshipClass $class) {
        // Check if already enrolled
        $existing = ClassParticipant::where('mentorship_class_id', $class->id)
                ->where('user_id', $user->id)
                ->first();

        if ($existing) {
            return redirect()->route('mentee.class-progress', [
                        'class' => $class->id,
                    ])->with('message', 'You are already enrolled in this class.');
        }

        // Enroll via service (handles exemption check + module progress creation)
        $participant = $this->enrollmentService->enrollInClass($user, $class, 'link');

        // Auto-mark attendance for enrollment via invite link (TDD rule)
        $this->attendanceService->markAttendance(
                classId: $class->id,
                sessionId: null, // enrollment-level attendance, not session-specific
                userId: $user->id,
                markedBy: $user->id,
                source: 'auto'
        );

        // Mark as active if class is active
        if ($class->status === 'active') {
            $participant->markActive();
        }

        return redirect()->route('mentee.class-progress', [
                    'class' => $class->id,
                ])->with('success', 'Successfully enrolled in the class!');
    }
}
