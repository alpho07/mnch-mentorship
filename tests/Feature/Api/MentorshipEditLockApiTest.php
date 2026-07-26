<?php

namespace Tests\Feature\Api;

use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipEditLockApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');
        $this->token = $this->mentor->createToken('test')->plainTextToken;
    }

    private function makeTraining(): Training
    {
        $facility = Facility::factory()->create();
        $program  = Program::factory()->create();

        return Training::create([
            'type'        => 'facility_mentorship',
            'title'       => 'Test Mentorship',
            'mentor_id'   => $this->mentor->id,
            'program_id'  => $program->id,
            'facility_id' => $facility->id,
            'status'      => 'active',
            'start_date'  => now(),
            'end_date'    => now()->addMonths(3),
        ]);
    }

    public function test_mentorship_update_blocked_once_a_class_is_completed(): void
    {
        $training = $this->makeTraining();
        MentorshipClass::create(['training_id' => $training->id, 'name' => 'Class 1', 'status' => 'completed']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}", ['max_participants' => 10])
             ->assertStatus(422);
    }

    public function test_program_change_blocked_once_a_class_is_active(): void
    {
        $training = $this->makeTraining();
        MentorshipClass::create(['training_id' => $training->id, 'name' => 'Class 1', 'status' => 'active']);
        $newProgram = Program::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}", ['program_id' => $newProgram->id])
             ->assertStatus(422);
    }

    public function test_other_fields_still_editable_once_a_class_is_active(): void
    {
        $training = $this->makeTraining();
        MentorshipClass::create(['training_id' => $training->id, 'name' => 'Class 1', 'status' => 'active']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}", ['max_participants' => 10])
             ->assertOk();
    }

    public function test_completed_class_cannot_be_updated(): void
    {
        $training = $this->makeTraining();
        $class = MentorshipClass::create(['training_id' => $training->id, 'name' => 'Class 1', 'status' => 'completed']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}/classes/{$class->id}", ['name' => 'Renamed'])
             ->assertStatus(422);
    }

    public function test_completed_class_cannot_be_deleted(): void
    {
        $training = $this->makeTraining();
        $class = MentorshipClass::create(['training_id' => $training->id, 'name' => 'Class 1', 'status' => 'completed']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->deleteJson("/api/v1/mentorships/{$training->id}/classes/{$class->id}")
             ->assertStatus(422);
    }
}
