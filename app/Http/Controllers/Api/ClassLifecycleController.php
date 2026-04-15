<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Services\ModuleUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassLifecycleController extends Controller
{
    use AuthorizesClassAccess;

    public function __construct(private readonly ModuleUsageService $moduleUsageService) {}

    /**
     * POST /api/v1/classes/{class}/start
     */
    public function start(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->canStart()) {
            return response()->json([
                'message' => "Class cannot be started. Status: {$class->status}. Ensure it has modules and enrolled mentees.",
            ], 422);
        }

        $class->start();
        $class->refresh();

        return response()->json(['data' => ['id' => $class->id, 'status' => $class->status]]);
    }

    /**
     * POST /api/v1/classes/{class}/end
     */
    public function end(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->canEnd()) {
            return response()->json([
                'message' => "Class cannot be ended. Status: {$class->status}.",
            ], 422);
        }

        $class->complete();
        $class->refresh();

        return response()->json(['data' => ['id' => $class->id, 'status' => $class->status]]);
    }

    /**
     * POST /api/v1/classes/{class}/mentees
     */
    public function enrollMentee(Request $request, MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $already = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $request->user_id)
            ->exists();

        abort_if($already, 409, 'User is already enrolled in this class.');

        $participant = ClassParticipant::create([
            'mentorship_class_id' => $class->id,
            'user_id'             => $request->user_id,
            'status'              => 'enrolled',
            'enrolled_at'         => now(),
        ]);

        $this->moduleUsageService->cascadeAllModulesToParticipant($class, $participant);

        $participant->load('user');

        return response()->json([
            'data' => [
                'participant_id' => $participant->id,
                'user_id'        => $participant->user_id,
                'name'           => $participant->user?->full_name ?? $participant->user?->name,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/classes/{class}/enrollment-link
     */
    public function enrollmentLink(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->enrollment_token) {
            $class->update(['enrollment_token' => Str::uuid(), 'enrollment_link_active' => true]);
            $class->refresh();
        }

        $url = url('/enroll/' . $class->enrollment_token);

        return response()->json([
            'data' => [
                'token'  => $class->enrollment_token,
                'url'    => $url,
                'active' => (bool) $class->enrollment_link_active,
            ],
        ]);
    }

    /**
     * POST /api/v1/classes/{class}/regenerate-token
     */
    public function regenerateToken(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);
        abort_if($class->status === 'completed' || $class->status === 'cancelled', 422, 'Cannot regenerate token for a completed class.');

        $token = Str::uuid();
        $class->update(['enrollment_token' => $token, 'enrollment_link_active' => true]);

        return response()->json([
            'data' => [
                'token'  => $token,
                'url'    => url('/enroll/' . $token),
                'active' => true,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/classes/{class}/mentees/{participant}
     */
    public function removeMentee(MentorshipClass $class, ClassParticipant $participant): JsonResponse
    {
        $this->authorizeClassAccess($class);

        abort_if($participant->mentorship_class_id !== $class->id, 422, 'Participant does not belong to this class.');
        abort_if($participant->status !== 'enrolled', 422, 'Cannot remove a mentee who has started modules.');

        $participant->delete();

        return response()->json(['message' => 'Mentee removed.']);
    }
}
