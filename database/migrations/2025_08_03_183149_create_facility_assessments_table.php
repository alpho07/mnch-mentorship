<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('assessor_id')->constrained('users')->onDelete('cascade');
            $table->date('assessment_date');
            $table->decimal('infrastructure_score', 5, 2)->nullable();
            $table->decimal('equipment_score', 5, 2)->nullable();
            $table->decimal('staff_capacity_score', 5, 2)->nullable();
            $table->decimal('training_environment_score', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->json('recommendations')->nullable();
            $table->date('next_assessment_due')->nullable();
            $table->text('assessment_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'assessment_date']);
            $table->index(['status', 'next_assessment_due']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_assessments');
    }
};
