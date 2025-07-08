<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_from_to_location_to_item_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_transactions', function (Blueprint $table) {
            $table->foreignId('from_location_id')->nullable()->after('inventory_item_id')->constrained('locations');
            $table->foreignId('to_location_id')->nullable()->after('from_location_id')->constrained('locations');
        });
    }

    public function down(): void
    {
        Schema::table('item_transactions', function (Blueprint $table) {
            $table->dropForeign(['from_location_id']);
            $table->dropForeign(['to_location_id']);
            $table->dropColumn(['from_location_id', 'to_location_id']);
        });
    }
};
