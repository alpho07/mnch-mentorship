<?php

// database/migrations/2024_07_07_000008_create_item_training_links_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('item_training_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained();
            $table->foreignId('program_id')->nullable()->constrained();
            $table->foreignId('module_id')->nullable()->constrained();
            $table->foreignId('topic_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('item_training_links');
    }
};

