<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalTrainingController extends Controller
{
    /**
     * GET /api/v1/trainings
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Training::with(['county', 'facility'])
            ->where('type', 'global_training')
            ->latest('start_date');

        // Admins and above-site roles see all; others see only their registrations
        if (!$user->isAboveSite()) {
            $query->whereHas('participants', fn($q) => $q->where('user_id', $user->id));
        }

        $trainings = $query->get()->map(fn(Training $t) => [
            'id'            => $t->id,
            'title'         => $t->title,
            'status'        => $t->status,
            'start_date'    => $t->start_date?->toDateString(),
            'end_date'      => $t->end_date?->toDateString(),
            'county'        => $t->county?->name,
            'location_type' => $t->location_type,
        ]);

        return response()->json(['data' => $trainings]);
    }

    /**
     * Abort 403 if the user is not permitted to view this training.
     * Above-site roles see all. Others must be registered participants.
     */
    private function authorizeTrainingAccess(Request $request, Training $training): void
    {
        if ($request->user()->isAboveSite()) return;

        $enrolled = $training->participants()
            ->where('user_id', $request->user()->id)
            ->exists();

        abort_if(!$enrolled, 403, 'You are not enrolled in this training.');
    }

    /**
     * GET /api/v1/trainings/{training}
     */
    public function show(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);
        $this->authorizeTrainingAccess($request, $training);
        $training->load(['county', 'facility', 'program']);

        return response()->json([
            'data' => [
                'id'               => $training->id,
                'title'            => $training->title,
                'status'           => $training->status,
                'start_date'       => $training->start_date?->toDateString(),
                'end_date'         => $training->end_date?->toDateString(),
                'county'           => $training->county?->name,
                'location_type'    => $training->location_type,
                'max_participants' => $training->max_participants,
                'description'      => $training->description,
            ],
        ]);
    }

    /**
     * GET /api/v1/trainings/{training}/participants
     */
    public function participants(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);
        $this->authorizeTrainingAccess($request, $training);

        $participants = $training->participants()
            ->with('user')
            ->get()
            ->map(fn($p) => [
                'id'                => $p->id,
                'name'              => $p->user?->name,
                'completion_status' => $p->completion_status ?? 'pending',
                'attendance_status' => $p->attendance_status ?? 'not_marked',
            ]);

        return response()->json(['data' => $participants]);
    }
}
