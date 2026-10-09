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
     * Run the database seeds for Realistic Operational Expenses (SRS M5 & Prototype UI alignment).
     */
    public function run(): void
    {
        $campuses = Campus::orderBy('id')->get();
        $categories = ExpenseCategory::all();
        $user = User::first();

        if ($campuses->isEmpty() || $categories->isEmpty() || !$user) {
            return;
        }

        // Clean previous expenses through Eloquent so ExpenseObserver cleans ledger entries properly
        foreach (Expense::all() as $oldExpense) {
            $oldExpense->delete();
        }

        $sampleExpenses = [
            [
                'category' => 'Bagor Karung',
                'amount' => 125000.00,
                'description' => 'Pembelian 50 lembar karung bagor pilah kapasitas 50kg untuk TPS',
            ],
            [
                'category' => 'Makan Minum',
                'amount' => 60000.00,
                'description' => 'Snack dan konsumsi air minum petugas pilah sampah TPS',
            ],
            [
                'category' => 'Pembelian Alat',
                'amount' => 175000.00,
                'description' => 'Pengadaan 4 pasang sarung tangan karet tebal dan 1 sekop sampah',
            ],
            [
                'category' => 'Upah Pilah',
                'amount' => 200000.00,
                'description' => 'Upah harian tenaga sortir dan pilah sampah anorganik',
            ],
            [
                'category' => 'BBM Operasional',
                'amount' => 50000.00,
                'description' => 'Pertalite 5 liter untuk mesin perajang daun dan pencacah organik',
            ],
            [
                'category' => 'Pakan Maggot',
                'amount' => 93000.00,
                'description' => 'Dedak / Polar 10kg + konsentrat pengembang biak larva BSF',
            ],
        ];

        $today = Carbon::today();

        foreach ($campuses as $campus) {
            $isMainCampus = ($campus->id == 4);
            $expenseDays = $isMainCampus ? [27, 24, 20, 17, 14, 11, 7, 3, 1] : [25, 17, 9, 2];

            foreach ($expenseDays as $idx => $dayOffset) {
                $expenseDate = $today->copy()->subDays($dayOffset)->format('Y-m-d');
                $sample = $sampleExpenses[($campus->id + $idx) % count($sampleExpenses)];
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
