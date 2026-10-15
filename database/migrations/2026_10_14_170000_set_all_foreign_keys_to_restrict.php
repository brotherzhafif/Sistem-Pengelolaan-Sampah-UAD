<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to enforce RESTRICT on all domain foreign keys.
     * Mencegah penghapusan cascading/loss data master & histori operasional.
     */
    public function up(): void
    {
        // 1. users.campus_id
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
        });

        // 2. waste_sources.campus_id
        Schema::table('waste_sources', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
        });

        // 3. weighing_sessions
        Schema::table('weighing_sessions', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropForeign(['waste_source_id']);
            $table->dropForeign(['created_by']);

            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
            $table->foreign('waste_source_id')->references('id')->on('waste_sources')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        // 4. weighing_items
        Schema::table('weighing_items', function (Blueprint $table) {
            $table->dropForeign(['weighing_session_id']);
            $table->dropForeign(['waste_type_id']);

            $table->foreign('weighing_session_id')->references('id')->on('weighing_sessions')->restrictOnDelete();
            $table->foreign('waste_type_id')->references('id')->on('waste_types')->restrictOnDelete();
        });

        // 5. sales
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropForeign(['buyer_id']);
            $table->dropForeign(['created_by']);

            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
            $table->foreign('buyer_id')->references('id')->on('buyers')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        // 6. sale_items
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['waste_type_id']);

            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
            $table->foreign('waste_type_id')->references('id')->on('waste_types')->restrictOnDelete();
        });

        // 7. financial_transactions
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropForeign(['created_by']);

            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        // 8. general_ledgers
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
        });

        // 9. pickups
        Schema::table('pickups', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['created_by']);

            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        // 10. expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['created_by']);

            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        // 11. kap_surveys
        Schema::table('kap_surveys', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
        });

        // 12. activity_logs
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['campus_id']);

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original settings if needed
    }
};

