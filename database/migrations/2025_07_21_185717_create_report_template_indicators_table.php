<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_template_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('indicator_id')->constrained()->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            // Use a short, custom name for the unique constraint
            $table->unique(['report_template_id', 'indicator_id'], 'rtpl_ind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_template_indicators');
    }
};
