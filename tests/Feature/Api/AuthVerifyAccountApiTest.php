<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthVerifyAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function signedParams(User $user): array
    {
        $url = URL::temporarySignedRoute('account.verify.show', now()->addDays(7), ['user' => $user->id]);
        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $query; // ['expires' => ..., 'signature' => ...]
    }

    public function test_verifies_pending_user_and_returns_token(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $params = $this->signedParams($user);

        $response = $this->postJson("/api/v1/auth/verify-account/{$user->id}", array_merge($params, [
            'password'              => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ]));

        $response->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Sup3rSecret', $user->password));
    }

    public function test_rejects_tampered_signature(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $params = $this->signedParams($user);
        $params['signature'] = 'not-a-real-signature';

        $this->postJson("/api/v1/auth/verify-account/{$user->id}", array_merge($params, [
            'password'              => 'Sup3rSecret',
            'password_confirmation' => 'Sup3rSecret',
        ]))->assertStatus(403);
    }

    public function test_rejects_weak_password(): void
    {
        $user = User::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $params = $this->signedParams($user);

        $this->postJson("/api/v1/auth/verify-account/{$user->id}", array_merge($params, [
            'password'              => 'lowercase',
            'password_confirmation' => 'lowercase',
        ]))->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}

