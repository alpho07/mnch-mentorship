<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mentorship_classes', function (Blueprint $table) {
            $table->timestamp('uninvited_mentee_reminder_sent_at')->nullable()->after('enrollment_link_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_classes', function (Blueprint $table) {
            $table->dropColumn('uninvited_mentee_reminder_sent_at');
        });
    }
};
