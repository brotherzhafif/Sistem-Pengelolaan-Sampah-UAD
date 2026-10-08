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
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // e.g. "Kampus 1", "Kampus 4 (Utama)"
            $table->string('code', 20)->unique(); // e.g. "KAMPUS-1", "KAMPUS-4"
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('campus_id')
                ->nullable()
                ->after('id')
                ->constrained('campuses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn('campus_id');
        });

        Schema::dropIfExists('campuses');
    }
};

