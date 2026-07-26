<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MainCadre;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Live "does this email already have an account" check while typing on
     * the Register screen. Only returns existence — no other user data —
     * to avoid leaking anything beyond what registration's own uniqueness
     * validation already reveals on submit.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        return response()->json([
            'exists' => User::where('email', $request->email)->exists(),
        ]);
    }

    /**
     * Live "is this phone already tied to an account" check while typing on
     * the Register screen. Same existence-only response as checkEmail().
     */
    public function checkPhone(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);

        return response()->json([
            'exists' => User::where('phone', $request->phone)->exists(),
        ]);
    }
}
