<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MainCadre;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated reference-data lookups for the mobile Register
 * screen only — mirrors App\Livewire\Auth\CustomRegister's field sources
 * exactly (MainCadre, not the assessment_cadres-backed Cadre model that
 * LookupController::cadres() uses for a different purpose).
 */
class RegisterLookupController extends Controller
{
    public function counties(): JsonResponse
    {
        return response()->json([
            'data' => County::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function cadres(): JsonResponse
    {
        return response()->json([
            'data' => MainCadre::where('is_active', true)
                ->orderBy('order')
                ->get(['id', 'name']),
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json([
            'data' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function facilitiesByCounty(County $county): JsonResponse
    {
        $facilities = Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $county->id))
            ->orderBy('name')
            ->get(['id', 'name', 'mfl_code'])
            ->map(fn (Facility $f) => [
                'id'       => $f->id,
                'name'     => $f->name,
                'mfl_code' => $f->mfl_code,
                'label'    => $f->mfl_code ? "{$f->mfl_code} - {$f->name}" : $f->name,
            ]);

        return response()->json(['data' => $facilities]);
    }
}
