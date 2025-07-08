<?php

// database/migrations/2024_07_07_000007_create_item_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('item_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('item_batch_id')->nullable()->constrained();
            $table->enum('type', ['receipt', 'issue', 'return', 'adjustment', 'transfer']);
            $table->integer('quantity');
            $table->foreignId('user_id')->constrained();
            $table->text('remarks')->nullable();
            $table->timestamp('transaction_date');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('item_transactions');
    }
};
