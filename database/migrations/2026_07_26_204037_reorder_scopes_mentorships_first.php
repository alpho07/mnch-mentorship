<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mobile scope tab order: Mentorships, Assessments, Trainings
     * (previously Assessments, Mentorships, Trainings).
     */
    public function up(): void
    {
        DB::table('scopes')->where('slug', 'mentorships')->update(['sort_order' => 1]);
        DB::table('scopes')->where('slug', 'assessments')->update(['sort_order' => 2]);
        DB::table('scopes')->where('slug', 'trainings')->update(['sort_order' => 3]);
    }

    public function down(): void
    {
        DB::table('scopes')->where('slug', 'assessments')->update(['sort_order' => 1]);
        DB::table('scopes')->where('slug', 'mentorships')->update(['sort_order' => 2]);
        DB::table('scopes')->where('slug', 'trainings')->update(['sort_order' => 3]);
    }
};
