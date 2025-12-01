<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            
            // For matrix questions
            $table->string('location')->nullable(); // 'Skills Lab', 'NBU', etc.
            
            // Response value
            $table->text('response_value')->nullable();
            $table->text('explanation')->nullable();
            
            // Scoring
            $table->decimal('score', 5, 2)->nullable();
            
            // Metadata
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['assessment_id', 'question_id']);
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_responses');
    }
};