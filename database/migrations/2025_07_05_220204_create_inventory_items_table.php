<?php

// database/migrations/2024_07_07_000004_create_inventory_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained();
            $table->string('unit_of_measure');
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->string('image_url')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('status', [
                'available', 'in_use', 'maintenance', 'disposed', 'lost'
            ])->default('available');
            $table->boolean('is_borrowable')->default(true);
            $table->foreignId('current_location_id')->nullable()->constrained('locations');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('last_tracked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('inventory_items');
    }
};
