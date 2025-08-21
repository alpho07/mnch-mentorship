<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('report_type', ['newborn', 'pediatric', 'general']);
            $table->enum('frequency', ['monthly', 'quarterly', 'annually'])->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->json('dhis2_mapping')->nullable(); // For future DHIS2 integration
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
