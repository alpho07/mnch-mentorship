<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function programs(): JsonResponse
    {
        $programs = Program::orderBy('name')->get(['id', 'name', 'description']);
        return response()->json(['data' => $programs]);
    }

    public function programModules(Program $program): JsonResponse
    {
        $modules = ProgramModule::where('program_id', $program->id)
            ->where('is_active', true)
            ->withCount(['moduleSessions as session_count' => fn($q) => $q->where('is_active', true)])
            ->orderBy('order_sequence')
            ->get()
            ->map(fn(ProgramModule $m) => [
                'id'             => $m->id,
                'name'           => $m->name,
                'description'    => $m->description,
                'order_sequence' => $m->order_sequence,
                'session_count'  => (int) $m->session_count,
            ]);

        return response()->json(['data' => $modules]);
    }

    public function counties(): JsonResponse
    {
        $counties = County::orderBy('name')->get(['id', 'name']);
        return response()->json(['data' => $counties]);
    }

    public function facilitiesByCounty(County $county): JsonResponse
    {
        $facilities = Facility::whereHas('subcounty', fn($q) => $q->where('county_id', $county->id))
            ->orderBy('name')
            ->get(['id', 'name', 'mfl_code'])
            ->map(fn(Facility $f) => [
                'id'       => $f->id,
                'name'     => $f->name,
                'mfl_code' => $f->mfl_code,
                'label'    => "{$f->mfl_code} — {$f->name}",
            ]);

        return response()->json(['data' => $facilities]);
    }

    public function userSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q'           => 'required|string|min:2',
            'facility_id' => 'nullable|integer',
        ]);

        $query = User::query()
            ->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->q . '%')
                  ->orWhere('last_name', 'like', '%' . $request->q . '%');
            })
            ->limit(30);

        if ($request->facility_id) {
            $query->whereHas('facilities', fn($q) => $q->where('facilities.id', $request->facility_id));
        }

        $users = $query->with('facilities')->get()->map(fn(User $u) => [
            'id'            => $u->id,
            'name'          => $u->full_name,
            'email'         => $u->email,
            'facility_name' => $u->facilities->first()?->name ?? '',
        ]);

        return response()->json(['data' => $users]);
    }
}
