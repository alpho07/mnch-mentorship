<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('indicator_id')->constrained()->onDelete('cascade');
            $table->integer('numerator')->nullable();
            $table->integer('denominator')->nullable();
            $table->decimal('calculated_value', 8, 4)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['monthly_report_id', 'indicator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_values');
    }
};
