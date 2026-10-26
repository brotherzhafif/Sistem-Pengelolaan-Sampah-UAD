<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add device tracking and anti-spam columns to kap_surveys.
     */
    public function up(): void
    {
        if (Schema::hasTable('kap_surveys')) {
            Schema::table('kap_surveys', function (Blueprint $table) {
                if (!Schema::hasColumn('kap_surveys', 'device_id')) {
                    $table->string('device_id', 64)->nullable()->after('survey_date')->index();
                }
                if (!Schema::hasColumn('kap_surveys', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('device_id');
                }
                if (!Schema::hasColumn('kap_surveys', 'user_agent')) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('kap_surveys')) {
            Schema::table('kap_surveys', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('kap_surveys', 'device_id')) {
                    $columns[] = 'device_id';
                }
                if (Schema::hasColumn('kap_surveys', 'ip_address')) {
                    $columns[] = 'ip_address';
                }
                if (Schema::hasColumn('kap_surveys', 'user_agent')) {
                    $columns[] = 'user_agent';
                }

                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
