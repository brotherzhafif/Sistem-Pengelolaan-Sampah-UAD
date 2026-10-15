<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to enforce RESTRICT on all Spatie permission foreign keys.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        // 1. model_has_permissions
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->dropForeign(['permission_id']);
            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->restrictOnDelete();
        });

        // 2. model_has_roles
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
            $table->dropForeign(['role_id']);
            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->restrictOnDelete();
        });

        // 3. role_has_permissions
        Schema::table($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->dropForeign(['permission_id']);
            $table->dropForeign(['role_id']);

            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->restrictOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->dropForeign(['permission_id']);
            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
            $table->dropForeign(['role_id']);
            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();
        });

        Schema::table($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->dropForeign(['permission_id']);
            $table->dropForeign(['role_id']);

            $table->foreign('permission_id')
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();
        });
    }
};
