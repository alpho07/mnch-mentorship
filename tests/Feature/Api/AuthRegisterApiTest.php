<?php

namespace Tests\Feature\Api;

use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MainCadre;
use App\Models\Subcounty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthRegisterApiTest extends TestCase
{
    use RefreshDatabase;

    // MainCadre, Department, and Subcounty have no factory classes in this
    // repo — create directly instead of Model::factory().
    private function validPayload(array $overrides = []): array
    {
        $county     = County::factory()->create();
        $subcounty  = Subcounty::create(['name' => 'Test Subcounty', 'county_id' => $county->id]);
        $facility   = Facility::factory()->create(['subcounty_id' => $subcounty->id]);
        $cadre      = MainCadre::create(['name' => 'Nurse', 'is_active' => true, 'order' => 1]);
        $department = Department::create(['name' => 'Maternity']);

        return array_merge([
            'first_name'    => 'Jane',
            'middle_name'   => '',
            'last_name'     => 'Mwangi',
            'email'         => 'jane.mwangi@example.test',
            'phone'         => '0712345678',
            'cadre_id'      => $cadre->id,
            'department_id' => $department->id,
            'role'          => 'mentee',
            'county_id'     => $county->id,
            'facility_id'   => $facility->id,
        ], $overrides);
    }

    public function test_registers_pending_user_and_sends_verification_email(): void
    {
        Mail::fake();

        $payload = $this->validPayload();

        $this->postJson('/api/v1/auth/register', $payload)
             ->assertCreated()
             ->assertJsonStructure(['message']);

        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('mentee'));
        $this->assertTrue($user->counties->contains('id', $payload['county_id']));
        $this->assertTrue($user->facilities->contains('id', $payload['facility_id']));

        Mail::assertSent(\App\Mail\AccountVerificationMail::class);
    }

    public function test_rejects_duplicate_email(): void
    {
        $existing = User::factory()->create();

        $this->postJson('/api/v1/auth/register', $this->validPayload(['email' => $existing->email]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_rejects_invalid_role(): void
    {
        $this->postJson('/api/v1/auth/register', $this->validPayload(['role' => 'super_admin']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['role']);
    }
}
