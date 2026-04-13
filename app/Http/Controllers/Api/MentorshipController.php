<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipClass;
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
    public function show(Request $request, Training $training): JsonResponse {
        abort_unless($training->type === 'facility_mentorship', 404);

        $this->authorize('view', $training);

        $training->loadCount('mentorshipClasses as class_count');

        return response()->json([
            'data' => [
                'id'          => $training->id,
                'title'       => $training->title,
                'status'      => $training->status,
                'class_count' => (int) $training->class_count,
            ],
        ]);
    }

    public function classes(Request $request, Training $training): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        $classes = $training->mentorshipClasses()
            ->withCount(['classModules', 'participants'])
            ->get()
            ->map(fn(MentorshipClass $c) => [
                'id'                  => $c->id,
                'name'                => $c->name,
                'status'              => $c->status,
                'start_date'          => $c->start_date?->toDateString(),
                'end_date'            => $c->end_date?->toDateString(),
                'module_count'        => $c->class_modules_count,
                'participant_count'   => $c->participants_count,
                'progress_percentage' => $c->progress_percentage,
            ]);

        return response()->json(['data' => $classes]);
    }

    public function classDetail(Request $request, Training $training, MentorshipClass $class): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        abort_if($class->training_id !== $training->id, 404);

        $class->load(['classModules.programModule', 'participants.user']);

        $modules = $class->classModules->map(fn($m) => [
            'id'             => $m->id,
            'name'           => $m->programModule?->name ?? 'Module ' . $m->order_sequence,
            'status'         => $m->status,
            'order_sequence' => $m->order_sequence,
            'started_at'     => $m->started_at?->toIso8601String(),
            'completed_at'   => $m->completed_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'id'                  => $class->id,
                'name'                => $class->name,
                'status'              => $class->status,
                'progress_percentage' => $class->progress_percentage,
                'participant_count'   => $class->participants->count(),
                'modules'             => $modules,
            ],
        ]);
    }

    private function authoriseMentorship(Request $request, Training $training): void
    {
        $userId = $request->user()->id;
        $isMentor = $training->mentor_id === $userId;
        $isCoMentor = $training->acceptedCoMentors()->where('user_id', $userId)->exists();
        abort_if(!$isMentor && !$isCoMentor, 403, 'Not authorised for this mentorship.');
        abort_if($training->type !== 'facility_mentorship', 404);
    }
}
