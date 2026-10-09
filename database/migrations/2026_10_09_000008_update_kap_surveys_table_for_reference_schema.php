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
        Schema::table('kap_surveys', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('faculty_unit');
            $table->boolean('has_attended_training')->default(false)->after('gender');
            $table->boolean('is_willing_volunteer')->default(false)->after('has_attended_training');
            $table->string('residence_type', 50)->nullable()->change();
            $table->json('satisfaction_responses')->nullable()->after('practice_responses');
            $table->decimal('satisfaction_score', 5, 2)->default(0.00)->after('practice_score');
            $table->json('barrier_responses')->nullable()->after('facility_responses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kap_surveys', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'has_attended_training',
                'is_willing_volunteer',
                'satisfaction_responses',
                'satisfaction_score',
                'barrier_responses',
            ]);
            $table->string('residence_type', 50)->nullable(false)->change();
        });
    }
};
