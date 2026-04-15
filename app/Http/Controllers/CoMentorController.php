<?php

namespace App\Http\Controllers;

use App\Models\MentorshipCoMentor;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CoMentorController extends Controller {

    /**
     * Show invitation page.
     * Only pending invitations with a valid token can be viewed.
     */
    public function show(string $token) {
        $invitation = MentorshipCoMentor::where('invitation_token', $token)
                ->with(['user', 'inviter'])
                ->firstOrFail();

        // Check if invitation is still actionable
        if ($invitation->status === 'revoked') {
            return view('co-mentor.invitation', [
                'invitation' => $invitation,
                'training' => null,
                'inviter' => $invitation->inviter,
                'token' => $token,
                'error' => 'This invitation has been revoked by the lead mentor.',
            ]);
        }

        if (!in_array($invitation->status, ['pending'])) {
            return view('co-mentor.invitation', [
                'invitation' => $invitation,
                'training' => null,
                'inviter' => $invitation->inviter,
                'token' => $token,
                'error' => 'This invitation has already been ' . $invitation->status . '.',
            ]);
        }

        $training = Training::findOrFail($invitation->training_id);
        $inviter = $invitation->inviter;

        return view('co-mentor.invitation', [
            'invitation' => $invitation,
            'training' => $training,
            'inviter' => $inviter,
            'token' => $token,
            'error' => null,
        ]);
    }

    /**
     * Process invitation acceptance or decline.
     */
    public function process(Request $request, string $token) {
        $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        $invitation = MentorshipCoMentor::where('invitation_token', $token)
                ->where('status', 'pending') // Only pending can be processed
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
