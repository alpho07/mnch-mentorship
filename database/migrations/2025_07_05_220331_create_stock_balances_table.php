<?php

// database/migrations/2024_07_07_000006_create_stock_balances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('item_batch_id')->nullable()->constrained();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'location_id', 'item_batch_id'], 'item_location_batch_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_balances');
    }
};
