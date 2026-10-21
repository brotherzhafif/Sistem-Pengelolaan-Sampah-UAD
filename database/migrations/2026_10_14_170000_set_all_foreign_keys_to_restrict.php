<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to enforce RESTRICT on all domain foreign keys.
     * Mencegah penghapusan cascading/loss data master & histori operasional.
     * Dibuat idempoten dan aman terhadap anomali skema lama di server produksi/lab.
     */
    public function up(): void
    {
        // 0. Kompatibilitas skema lama (keuangan -> financial_transactions, buku_besar -> general_ledgers)
        $this->handleLegacyTableRenames();

        // Helper closure untuk migrasi FK ke RESTRICT secara defensif & idempoten
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
                        // Drop FK lama yang bertentangan (misal cascade / set null) dengan nama constraint asli
                        try {
                            Schema::table($table, function (Blueprint $t) use ($fk) {
                                $t->dropForeign($fk['name']);
                            });
                        } catch (\Throwable $e) {
                            // Abaikan bila FK sudah terhapus
                        }
                    }
                }
            }

            if ($alreadyRestricted) {
                return;
            }

            // Bersihkan baris data yatim (orphaned rows) agar tidak memicu error MySQL 1452
            try {
                $validParentIds = DB::table($foreignTable)->pluck($foreignColumn);
                if ($validParentIds->isNotEmpty()) {
                    $orphanQuery = DB::table($table)
                        ->whereNotNull($column)
                        ->whereNotIn($column, $validParentIds);

                    if ($orphanQuery->exists()) {
                        $columnDetails = Schema::getColumns($table);
                        $isNullable = false;
                        foreach ($columnDetails as $col) {
                            if ($col['name'] === $column) {
                                $isNullable = $col['nullable'] ?? false;
                                break;
                            }
                        }

                        if ($isNullable) {
                            $orphanQuery->update([$column => null]);
                        } else {
                            $orphanQuery->update([$column => $validParentIds->first()]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fallback graceful
            }

            // Pasang foreign key baru dengan restrictOnDelete
            try {
                Schema::table($table, function (Blueprint $t) use ($column, $foreignTable, $foreignColumn) {
                    $t->foreign($column)
                        ->references($foreignColumn)
                        ->on($foreignTable)
                        ->restrictOnDelete();
                });
            } catch (\Throwable $e) {
                // Toleransi jika constraint telah dibuat sebelumnya
            }
        };

        // 1. users.campus_id
        $enforceRestrictForeignKey('users', 'campus_id', 'campuses');

        // 2. waste_sources.campus_id
        $enforceRestrictForeignKey('waste_sources', 'campus_id', 'campuses');

        // 3. weighing_sessions
        $enforceRestrictForeignKey('weighing_sessions', 'campus_id', 'campuses');
        $enforceRestrictForeignKey('weighing_sessions', 'waste_source_id', 'waste_sources');
        $enforceRestrictForeignKey('weighing_sessions', 'created_by', 'users');

        // 4. weighing_items
        $enforceRestrictForeignKey('weighing_items', 'weighing_session_id', 'weighing_sessions');
        $enforceRestrictForeignKey('weighing_items', 'waste_type_id', 'waste_types');

        // 5. sales
        $enforceRestrictForeignKey('sales', 'campus_id', 'campuses');
        $enforceRestrictForeignKey('sales', 'buyer_id', 'buyers');
        $enforceRestrictForeignKey('sales', 'created_by', 'users');

        // 6. sale_items
        $enforceRestrictForeignKey('sale_items', 'sale_id', 'sales');
        $enforceRestrictForeignKey('sale_items', 'waste_type_id', 'waste_types');

        // 7. financial_transactions
        $enforceRestrictForeignKey('financial_transactions', 'campus_id', 'campuses');
        $enforceRestrictForeignKey('financial_transactions', 'created_by', 'users');

        // 8. general_ledgers
        $enforceRestrictForeignKey('general_ledgers', 'campus_id', 'campuses');

        // 9. pickups
        $enforceRestrictForeignKey('pickups', 'campus_id', 'campuses');
        $enforceRestrictForeignKey('pickups', 'vendor_id', 'vendors');
        $enforceRestrictForeignKey('pickups', 'created_by', 'users');

        // 10. expenses
        $enforceRestrictForeignKey('expenses', 'campus_id', 'campuses');
        $enforceRestrictForeignKey('expenses', 'expense_category_id', 'expense_categories');
        $enforceRestrictForeignKey('expenses', 'created_by', 'users');

        // 11. kap_surveys
        $enforceRestrictForeignKey('kap_surveys', 'campus_id', 'campuses');

        // 12. activity_logs
        $enforceRestrictForeignKey('activity_logs', 'user_id', 'users');
        $enforceRestrictForeignKey('activity_logs', 'campus_id', 'campuses');
    }

    /**
     * Handle backward compatibility table & column renames if lab server has legacy tables.
     */
    protected function handleLegacyTableRenames(): void
    {
        // Renaming keuangan -> financial_transactions jika masih tabel lama
        if (Schema::hasTable('keuangan') && !Schema::hasTable('financial_transactions')) {
            Schema::rename('keuangan', 'financial_transactions');
        }

        if (Schema::hasTable('financial_transactions')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('financial_transactions', 'tanggal') && !Schema::hasColumn('financial_transactions', 'transaction_date')) {
                    $table->renameColumn('tanggal', 'transaction_date');
                }
                if (Schema::hasColumn('financial_transactions', 'jenis') && !Schema::hasColumn('financial_transactions', 'type')) {
                    $table->renameColumn('jenis', 'type');
                }
                if (Schema::hasColumn('financial_transactions', 'sumber') && !Schema::hasColumn('financial_transactions', 'source')) {
                    $table->renameColumn('sumber', 'source');
                }
                if (Schema::hasColumn('financial_transactions', 'nominal') && !Schema::hasColumn('financial_transactions', 'amount')) {
                    $table->renameColumn('nominal', 'amount');
                }
                if (Schema::hasColumn('financial_transactions', 'keterangan') && !Schema::hasColumn('financial_transactions', 'description')) {
                    $table->renameColumn('keterangan', 'description');
                }
            });

            // Normalisasi nilai enum jika ada data legacy
            try {
                DB::table('financial_transactions')->where('type', 'K')->update(['type' => 'credit']);
                DB::table('financial_transactions')->where('type', 'D')->update(['type' => 'debit']);
                DB::table('financial_transactions')->where('source', 'penjualan')->update(['source' => 'sales']);
                DB::table('financial_transactions')->where('source', 'pengangkutan')->update(['source' => 'pickup']);
                DB::table('financial_transactions')->where('source', 'operasional')->update(['source' => 'operational']);
                DB::table('financial_transactions')->where('source', 'saldo_awal')->update(['source' => 'initial_balance']);
            } catch (\Throwable $e) {
                // Abaikan jika tipe data kolom atau nilai sudah termigrasi
            }
        }

        // Renaming buku_besar -> general_ledgers jika masih tabel lama
        if (Schema::hasTable('buku_besar') && !Schema::hasTable('general_ledgers')) {
            Schema::rename('buku_besar', 'general_ledgers');
        }

        if (Schema::hasTable('general_ledgers')) {
            Schema::table('general_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('general_ledgers', 'tanggal') && !Schema::hasColumn('general_ledgers', 'ledger_date')) {
                    $table->renameColumn('tanggal', 'ledger_date');
                }
                if (Schema::hasColumn('general_ledgers', 'saldo_awal') && !Schema::hasColumn('general_ledgers', 'opening_balance')) {
                    $table->renameColumn('saldo_awal', 'opening_balance');
                }
                if (Schema::hasColumn('general_ledgers', 'total_kredit') && !Schema::hasColumn('general_ledgers', 'total_credit')) {
                    $table->renameColumn('total_kredit', 'total_credit');
                }
                if (Schema::hasColumn('general_ledgers', 'total_debet') && !Schema::hasColumn('general_ledgers', 'total_debit')) {
                    $table->renameColumn('total_debet', 'total_debit');
                }
                if (Schema::hasColumn('general_ledgers', 'saldo_akhir') && !Schema::hasColumn('general_ledgers', 'closing_balance')) {
                    $table->renameColumn('saldo_akhir', 'closing_balance');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original settings if needed
    }
};
