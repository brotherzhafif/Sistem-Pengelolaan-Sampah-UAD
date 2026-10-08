<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds for Realistic Operational Expenses (SRS M5).
     */
    public function run(): void
    {
        $campuses = Campus::all();
        $categories = ExpenseCategory::all();
        $user = User::first();

        if ($campuses->isEmpty() || $categories->isEmpty() || !$user) {
            return;
        }

        $sampleExpenses = [
            [
                'category' => 'Pembelian Plastik / Bagor / Karung',
                'amount' => 125000.00,
                'description' => 'Pembelian 50 lembar karung bagor pilah kapasitas 50kg untuk TPS',
            ],
            [
                'category' => 'Makan Minum Tenaga TPS',
                'amount' => 60000.00,
                'description' => 'Snack dan konsumsi air minum 4 petugas pilah sampah TPS',
            ],
            [
                'category' => 'Pembelian Alat (Sekop, Cangkul, Sarung Tangan)',
                'amount' => 175000.00,
                'description' => 'Pengadaan 4 pasang sarung tangan karet tebal dan 1 sekop sampah',
            ],
            [
                'category' => 'Upah Pilah TPS',
                'amount' => 200000.00,
                'description' => 'Upah harian tenaga sortir dan pilah sampah anorganik',
            ],
            [
                'category' => 'BBM Operasional Mesin Cacah',
                'amount' => 50000.00,
                'description' => 'Pertalite 5 liter untuk mesin perajang daun dan pencacah organik',
            ],
        ];

        // Seed data pengeluaran realistis pada beberapa kampus
        foreach ($campuses->take(3) as $cIndex => $campus) {
            for ($i = 3; $i >= 1; $i--) {
                $expenseDate = Carbon::now()->subDays($i)->format('Y-m-d');
                $sample = $sampleExpenses[($cIndex + $i) % count($sampleExpenses)];
                $category = $categories->firstWhere('name', $sample['category']) ?? $categories->first();

                Expense::create([
                    'campus_id' => $campus->id,
                    'expense_category_id' => $category->id,
                    'expense_date' => $expenseDate,
                    'amount' => $sample['amount'],
                    'description' => $sample['description'] . ' (' . $campus->name . ')',
                    'created_by' => $user->id,
                ]);
            }
        }
    }
}

