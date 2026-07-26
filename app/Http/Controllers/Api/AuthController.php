<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\Api\UserResource;
use App\Mail\AccountVerificationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {

    /**
     * POST /api/v1/auth/login
     *
     * Returns a Sanctum token + user object.
     * The mobile app stores the token in secure storage and sends it
     * as "Authorization: Bearer {token}" on all subsequent requests.
     */
    public function login(LoginRequest $request): JsonResponse {
        $user = User::with(['facility.subcounty.county', 'roles'])
                ->where('email', $request->email)
                ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                        'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === 'inactive') {
            return response()->json([
                        'message' => 'Your account has been deactivated. Contact your administrator.',
                            ], 403);
        }

        // Revoke previous mobile tokens for this device if device_name is sent
        if ($request->filled('device_name')) {
            $user->tokens()
                    ->where('name', $request->device_name)
                    ->delete();
        }

        $tokenName = $request->input('device_name', 'mobile-app');
        $abilities = $this->resolveAbilities($user);
        $token = $user->createToken($tokenName, $abilities);

        return response()->json([
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'user' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/register
     *
     * Mirrors App\Livewire\Auth\CustomRegister::register() — creates the
     * account as "pending" with a random unusable password and emails a
     * signed verification link (see verifyAccount()). No token is returned;
     * the account can't log in until the link is used to set a real password.
     */
    public function register(RegisterRequest $request): JsonResponse {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name'    => ucfirst(strtolower(trim($data['first_name']))),
                'middle_name'   => filled($data['middle_name'] ?? null)
                    ? ucfirst(strtolower(trim($data['middle_name'])))
                    : null,
                'last_name'     => ucfirst(strtolower(trim($data['last_name']))),
                'name'          => trim(collect([
                    $data['first_name'],
                    $data['middle_name'] ?? null,
                    $data['last_name'],
                ])->filter()->map(fn ($n) => ucfirst(strtolower(trim($n))))->implode(' ')),
                'email'         => $data['email'],
                'phone'         => $data['phone'],
                'cadre_id'      => $data['cadre_id'],
                'department_id' => $data['department_id'],
                'facility_id'   => $data['facility_id'],
                'password'      => Hash::make(Str::random(32)),
                'status'        => 'pending',
            ]);

            $user->assignRole($data['role']);
            $user->counties()->sync([$data['county_id']]);
            $user->facilities()->sync([$data['facility_id']]);

            return $user;
        });

        $user->load('roles');
        Mail::to($user->email)->send(new AccountVerificationMail($user));

        return response()->json([
            'message' => "Registration successful. Check {$user->email} for a verification link to set your password.",
        ], 201);
    }

    /**
     * POST /api/v1/auth/verify-account/{user}
     *
     * Mobile equivalent of AccountVerificationController::update() — validates
     * the same signed URL (expires/signature) emailed by AccountVerificationMail
     * (regenerated here against the "account.verify.show" route so the check is
     * identical to the web flow), sets the user's real password, marks them
     * verified/active, and returns a login token so the app can go straight
     * into the authenticated state.
     */
    public function verifyAccount(Request $request, User $user): JsonResponse {
        $verifyUrl = URL::route('account.verify.show', [
            'user'      => $user->id,
            'expires'   => $request->input('expires'),
            'signature' => $request->input('signature'),
        ]);

        if (! Request::create($verifyUrl, 'GET')->hasValidSignature()) {
            return response()->json(['message' => 'This verification link is invalid or has expired.'], 403);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'This account is already verified. Please log in.'], 409);
        }

        $request->validate([
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/[A-Z]/', 'regex:/[0-9]/',
            ],
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter and one number.',
            'password.min'   => 'Password must be at least 8 characters.',
        ]);

        $user->update([
            'password'          => Hash::make($request->password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status'            => 'active',
        ]);

        $user->load(['facility.subcounty.county', 'roles']);
        $token = $user->createToken('mobile-app', $this->resolveAbilities($user));

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revokes the current token.
     */
    public function logout(Request $request): JsonResponse {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * POST /api/v1/auth/logout-all
     * Revokes ALL tokens for the user (sign out from all devices).
     */
    public function logoutAll(Request $request): JsonResponse {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    /**
     * GET /api/v1/auth/me
     * Returns the current authenticated user.
     */
    public function me(Request $request): JsonResponse {
        $user = $request->user()->load(['facility.subcounty.county', 'roles']);

        return response()->json([
                    'user' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     * Rotates the current token (revoke + issue new one).
     */
    public function refresh(Request $request): JsonResponse {
        $user = $request->user();
        $tokenName = $request->user()->currentAccessToken()->name;
        $abilities = $this->resolveAbilities($user);

        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken($tokenName, $abilities);

        return response()->json([
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
        ]);
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
                    'message' => $status === Password::RESET_LINK_SENT ? 'Password reset link sent to your email.' : 'Unable to send reset link. Please check the email address.',
                        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                    $user->tokens()->delete(); // revoke all tokens on reset
                }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Password reset successfully. Please login.']);
    }

    /**
     * Map user roles → Sanctum token abilities.
     */
    private function resolveAbilities(User $user): array {
        $roleAbilities = [
            'super_admin' => ['*'],
            'admin' => ['assessments:*', 'sections:read', 'reports:read', 'profile:*'],
            'division_staff' => ['assessments:read', 'sections:read', 'reports:read', 'profile:*'],
            'mentor' => ['assessments:*', 'sections:read', 'reports:read', 'profile:*'],
            'mentee' => ['sections:read', 'profile:read'],
        ];

        $roles = $user->getRoleNames()->toArray();

        foreach ($roleAbilities as $role => $abilities) {
            if (in_array($role, $roles)) {
                return $abilities;
            }
        }

        return ['sections:read', 'profile:read'];
    }
}
