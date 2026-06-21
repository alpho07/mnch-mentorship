<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Database\Seeders\AssessmentQuestionConfigSeeder;
use Database\Seeders\AmbuBagCommoditySeeder;
use Database\Seeders\ProgramModulesSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
           // RolePermissionSeeder::class,
            //NationalMentorsSeeder::class, // Run after roles are created
            MenteeSeeder::class,
            ProgramModulesSeeder::class,   // Programs + modules required for mentorship creation
            PptxResourceSeeder::class,     // Newborn Care + Infant & Child PPTX slide placeholders
            // Assessment configuration seeders — safe to re-run at any time:
            // AssessmentQuestionConfigSeeder::class,  // Sets explanation fields + mortality question type
            // AmbuBagCommoditySeeder::class,           // Splits generic Ambu Bag into 250ml/500ml/1500ml
        ]);
    }
} 
