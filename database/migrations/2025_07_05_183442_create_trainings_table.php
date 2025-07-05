<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Training title
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete(); // If modules table exists
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete(); // User who is main facilitator
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('methodology')->nullable();
            $table->text('approach')->nullable();
            $table->json('resources')->nullable(); // For storing file links or references
            $table->json('equipment')->nullable(); // For storing equipment IDs or info
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
