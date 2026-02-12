<?php

namespace App\Http\Controllers;

use App\Models\MentorshipClass;
use App\Models\ClassParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MenteeEnrollmentController extends Controller {

    /**
     * Handle enrollment link access
     */
    public function enroll(Request $request, string $token) {
        // Find class by enrollment token
        $class = MentorshipClass::where('enrollment_token', $token)
                ->where('enrollment_link_active', true)
                ->with(['training', 'classModules.programModule'])
                ->firstOrFail();

        // Check if class is accepting enrollments
        if ($class->status === 'completed' || $class->status === 'cancelled') {
            return view('mentee.enrollment-closed', [
                'class' => $class,
                'message' => 'This class is no longer accepting enrollments.',
            ]);
        }

        // If user is logged in
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
     * Process enrollment submission
     */
    public function processEnrollmentSubmission(Request $request, string $token) {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $class = MentorshipClass::where('enrollment_token', $token)
                ->where('enrollment_link_active', true)
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
     * Process actual enrollment
     */
    private function processEnrollment(User $user, MentorshipClass $class) {
        // Check if already enrolled
        $existing = ClassParticipant::where('mentorship_class_id', $class->id)
                ->where('user_id', $user->id)
                ->first();

        if ($existing) {
            // Already enrolled - show their progress
            return redirect()->route('mentee.class-progress', [
                        'class' => $class->id,
                    ])->with('message', 'You are already enrolled in this class.');
        }

        // Get modules the user has completed in previous classes
        $completedModuleIds = $this->getUserCompletedModules($user->id);

        // Enroll the user
        $participant = ClassParticipant::create([
            'mentorship_class_id' => $class->id,
            'user_id' => $user->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        // Create module progress records
        foreach ($class->classModules as $classModule) {
            $isCompleted = in_array($classModule->program_module_id, $completedModuleIds);

            DB::table('mentee_module_progress')->insert([
                'class_participant_id' => $participant->id,
                'class_module_id' => $classModule->id,
                'status' => $isCompleted ? 'exempted' : 'not_started',
                'completed_in_previous_class' => $isCompleted,
                'exempted_at' => $isCompleted ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Mark as active if class is active
        if ($class->status === 'active') {
            $participant->markActive();
        }

        return redirect()->route('mentee.class-progress', [
                    'class' => $class->id,
                ])->with('success', 'Successfully enrolled in the class!');
    }

    /**
     * Get modules user has completed in any previous class
     */
    private function getUserCompletedModules(int $userId): array {
        return DB::table('class_participants')
                        ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
                        ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
                        ->where('class_participants.user_id', $userId)
                        ->where('mentee_module_progress.status', 'completed')
                        ->pluck('class_modules.program_module_id')
                        ->unique()
                        ->toArray();
    }
}
