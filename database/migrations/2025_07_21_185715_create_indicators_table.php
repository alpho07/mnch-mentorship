<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->text('numerator_description');
            $table->text('denominator_description')->nullable();
            $table->enum('calculation_type', ['percentage', 'count', 'rate', 'ratio']);
            $table->string('source_document')->nullable();
            $table->decimal('target_value', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('dhis2_mapping')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
