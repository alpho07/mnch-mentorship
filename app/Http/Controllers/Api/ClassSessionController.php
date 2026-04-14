<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    use AuthorizesClassAccess;

    /**
     * PUT /api/v1/sessions/{session}
     */
    public function update(Request $request, ClassSession $session): JsonResponse
    {
        $this->authorizeModuleAccess($session->classModule);

        $request->validate([
            'actual_date'      => 'nullable|date',
            'actual_time'      => 'nullable|string|max:10',
            'location'         => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
            'facilitator_id'   => 'nullable|integer|exists:users,id',
            'attendance_taken'  => 'nullable|boolean',
            'status'           => 'nullable|in:scheduled,in_progress,completed',
        ]);

        $data = $request->only(['actual_date', 'actual_time', 'location', 'notes', 'attendance_taken', 'status']);
        $data['facilitator_id'] = $request->facilitator_id ?? $request->user()->id;

        $session->update($data);
        $session->refresh();

        return response()->json([
            'data' => [
                'id'               => $session->id,
                'title'            => $session->title,
                'actual_date'      => $session->actual_date?->toDateString(),
                'actual_time'      => $session->actual_time,
                'location'         => $session->location,
                'notes'            => $session->notes,
                'attendance_taken'  => $session->attendance_taken,
                'status'           => $session->status,
            ],
        ]);
    }
}
