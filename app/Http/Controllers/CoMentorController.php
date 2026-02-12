<?php

namespace App\Http\Controllers;

use App\Models\MentorshipCoMentor;
use App\Models\MentorshipTraining;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CoMentorController extends Controller
{
    public function show(string $token)
    {
        $invitation = MentorshipCoMentor::where('invitation_token', $token)
            ->whereIn('status', ['pending'])
            ->firstOrFail();

        $training = MentorshipTraining::findOrFail($invitation->training_id);
        $inviter = \App\Models\User::find($invitation->invited_by);

        return view('co-mentor.invitation', [
            'invitation' => $invitation,
            'training' => $training,
            'inviter' => $inviter,
            'token' => $token,
        ]);
    }

    public function process(Request $request, string $token)
    {
        $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        $invitation = MentorshipCoMentor::where('invitation_token', $token)
            ->whereIn('status', ['pending'])
            ->firstOrFail();

        // Ensure authenticated user matches invitation
        if (Auth::id() !== $invitation->user_id) {
            abort(403, 'This invitation is not for you.');
        }

        DB::beginTransaction();
        try {
            if ($request->action === 'accept') {
                $invitation->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);

                DB::commit();

                return redirect()
                    ->route('filament.admin.pages.dashboard')
                    ->with('success', 'You are now a co-mentor for this training!');
            } else {
                $invitation->update([
                    'status' => 'declined',
                ]);

                DB::commit();

                return redirect()
                    ->route('filament.admin.pages.dashboard')
                    ->with('info', 'Invitation declined.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to process invitation: ' . $e->getMessage()]);
        }
    }
}