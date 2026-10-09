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
        Schema::create('kap_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->onDelete('cascade');
            $table->string('respondent_name')->nullable();
            $table->string('respondent_identifier')->nullable(); // NIM / NIP
            $table->string('respondent_role', 30); // mahasiswa, dosen, tendik
            $table->string('faculty_unit', 100); // Fakultas / Program Studi / Biro
            $table->string('residence_type', 50); // kos, asrama, rumah_sendiri, kontrakan
            
            // Raw Likert Responses (Array of ratings 1 - 5)
            $table->json('knowledge_responses');
            $table->json('attitude_responses');
            $table->json('practice_responses');
            $table->json('facility_responses')->nullable();

            // Computed Scores (0.00 - 100.00)
            $table->decimal('knowledge_score', 5, 2)->default(0.00);
            $table->decimal('attitude_score', 5, 2)->default(0.00);
            $table->decimal('practice_score', 5, 2)->default(0.00);
            $table->decimal('overall_score', 5, 2)->default(0.00);

            // Category classification
            $table->string('category', 30)->default('sedang'); // sangat_baik, sedang, kurang
            $table->text('feedback')->nullable();
            $table->date('survey_date');

            $table->timestamps();

            $table->index(['campus_id', 'survey_date']);
            $table->index(['respondent_role', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kap_surveys');
    }
};

