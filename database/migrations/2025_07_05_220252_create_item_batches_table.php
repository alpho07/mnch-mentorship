<?php

// database/migrations/2024_07_07_000005_create_item_batches_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained();
            $table->string('batch_no');
            $table->date('expiry_date')->nullable();
            $table->integer('initial_quantity');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('item_batches');
    }
};

