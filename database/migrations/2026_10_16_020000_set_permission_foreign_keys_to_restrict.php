<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to enforce RESTRICT on all Spatie permission foreign keys.
     * Dibuat idempoten dan defensif agar aman pada lingkungan CI/CD & database lab.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        $enforceRestrictForeignKey = function (
            string $table,
            string $column,
            string $foreignTable,
            string $foreignColumn = 'id'
        ): void {
            if (!Schema::hasTable($table) || !Schema::hasTable($foreignTable)) {
                return;
            }

            if (!Schema::hasColumn($table, $column) || !Schema::hasColumn($foreignTable, $foreignColumn)) {
                return;
            }

            $existingFks = Schema::getForeignKeys($table);
            $alreadyRestricted = false;

            foreach ($existingFks as $fk) {
                if (in_array($column, $fk['columns'] ?? [])) {
                    $onDelete = strtolower($fk['on_delete'] ?? '');
                    $targetTable = $fk['foreign_table'] ?? '';

                    if (($onDelete === 'restrict' || $onDelete === 'no action') && $targetTable === $foreignTable) {
                        $alreadyRestricted = true;
                    } else {
                        try {
                            Schema::table($table, function (Blueprint $t) use ($fk) {
                                $t->dropForeign($fk['name']);
                            });
                        } catch (\Throwable $e) {
                            // Abaikan jika FK sudah terhapus
                        }
                    }
                }
            }

            if ($alreadyRestricted) {
                return;
            }

            // Bersihkan baris data yatim (orphaned rows) jika ada
            try {
                $validParentIds = DB::table($foreignTable)->pluck($foreignColumn);
                if ($validParentIds->isNotEmpty()) {
                    $orphanQuery = DB::table($table)
                        ->whereNotNull($column)
                        ->whereNotIn($column, $validParentIds);

                    if ($orphanQuery->exists()) {
                        $orphanQuery->delete();
                    }
                }
            } catch (\Throwable $e) {
                // Fallback
            }

            // Pasang foreign key baru dengan restrict
            try {
                Schema::table($table, function (Blueprint $t) use ($column, $foreignTable, $foreignColumn) {
                    $t->foreign($column)
                        ->references($foreignColumn)
                        ->on($foreignTable)
                        ->restrictOnDelete();
                });
            } catch (\Throwable $e) {
                // Toleransi jika constraint sudah terpasang
            }
        };

        // 1. model_has_permissions
        if (!empty($tableNames['model_has_permissions']) && !empty($tableNames['permissions'])) {
            $enforceRestrictForeignKey($tableNames['model_has_permissions'], 'permission_id', $tableNames['permissions']);
        }

        // 2. model_has_roles
        if (!empty($tableNames['model_has_roles']) && !empty($tableNames['roles'])) {
            $enforceRestrictForeignKey($tableNames['model_has_roles'], 'role_id', $tableNames['roles']);
        }

        // 3. role_has_permissions
        if (!empty($tableNames['role_has_permissions']) && !empty($tableNames['permissions']) && !empty($tableNames['roles'])) {
            $enforceRestrictForeignKey($tableNames['role_has_permissions'], 'permission_id', $tableNames['permissions']);
            $enforceRestrictForeignKey($tableNames['role_has_permissions'], 'role_id', $tableNames['roles']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to cascade if needed
    }
};
