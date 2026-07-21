<?php

use App\Models\ProgramModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->renameModule('Infant and Child Care', 'Basic Life Support for Infant/Child', 'Basic Life Support for Infant/Child (BLS)');
            $this->renameModule('Infant and Child Care', 'Management of Severe Acute Malnutrition in Infants and Children Aged 6-59 Months', 'Management of Severe Acute Malnutrition in Infants and Children Aged 6-59 Months (SAM)');
            $this->renameModule('Infant and Child Care', 'Introduction to Type 1 Diabetes, Diabetic Ketoacidosis and Routine Care in Type 1 Diabetes', 'Introduction to Type 1 Diabetes, Diabetic Ketoacidosis and Routine Care in Type 1 Diabetes (T1DM, DKA)');
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->renameModule('Infant and Child Care', 'Basic Life Support for Infant/Child (BLS)', 'Basic Life Support for Infant/Child');
            $this->renameModule('Infant and Child Care', 'Management of Severe Acute Malnutrition in Infants and Children Aged 6-59 Months (SAM)', 'Management of Severe Acute Malnutrition in Infants and Children Aged 6-59 Months');
            $this->renameModule('Infant and Child Care', 'Introduction to Type 1 Diabetes, Diabetic Ketoacidosis and Routine Care in Type 1 Diabetes (T1DM, DKA)', 'Introduction to Type 1 Diabetes, Diabetic Ketoacidosis and Routine Care in Type 1 Diabetes');
        });
    }

    private function renameModule(string $programName, string $from, string $to): void
    {
        $programId = DB::table('programs')->where('name', $programName)->value('id');

        if (!$programId) {
            return;
        }

        ProgramModule::where('program_id', $programId)
            ->where('name', $from)
            ->update(['name' => $to]);
    }
};
