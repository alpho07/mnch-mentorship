<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorshipController extends Controller {

    /**
     * List facility_mentorship trainings for the authenticated user.
     */
    public function index(Request $request): JsonResponse {
        $user = $request->user();

        $mentorships = Training::query()
            ->where('type', 'facility_mentorship')
            ->where('mentor_id', $user->id)
            ->withCount('mentorshipClasses as class_count')
            ->get(['id', 'title', 'status', 'mentor_id']);

        $data = $mentorships->map(fn (Training $t) => [
            'id'          => $t->id,
            'title'       => $t->title,
            'status'      => $t->status,
            'class_count' => (int) $t->class_count,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Show a single mentorship training.
     */
    public function show(Request $request, Training $mentorship): JsonResponse {
        abort_unless($mentorship->type === 'facility_mentorship', 404);

        $this->authorize('view', $mentorship);

        $mentorship->loadCount('mentorshipClasses as class_count');

        return response()->json([
            'data' => [
                'id'          => $mentorship->id,
                'title'       => $mentorship->title,
                'status'      => $mentorship->status,
                'class_count' => (int) $mentorship->class_count,
            ],
        ]);
    }
}
