<?php

namespace Tests\Feature;

use App\Http\Controllers\OrganicProcessingController;
use App\Http\Controllers\PickupController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\WeighingController;
use App\Models\Buyer;
use App\Models\Campus;
use App\Models\OrganicOutputCategory;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteSource;
use App\Models\WasteType;
use Database\Seeders\CampusSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInfographicTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_renders_honest_empty_state_without_authentication(): void
    {
        $this->seed([
            CampusSeeder::class,
            RolePermissionSeeder::class,
            MasterDataSeeder::class,
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Infografik Sampah Terkelola vs Tidak Terkelola')
            ->assertSee('Belum Ada Data Penanganan Keluar yang Tercatat')
            ->assertDontSee('Rp ');
    }

    public function test_public_infographic_computes_managed_vs_unmanaged_without_financial_leaks(): void
    {
        $this->seed([
            CampusSeeder::class,
            RolePermissionSeeder::class,
            MasterDataSeeder::class,
        ]);

        $campuses = Campus::orderBy('id')->get();
        $campus1 = $campuses->first();
        $campus2 = $campuses->skip(1)->first() ?? $campus1;

        $admin = User::factory()->create(['campus_id' => null]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $kardus = WasteType::where('name', 'Kardus')->firstOrFail();
        $sisaMakanan = WasteType::where('name', 'Organik Sisa Makanan')->firstOrFail();
        $residu = WasteType::where('name', 'Residu Campur')->firstOrFail();
        $pupuk = OrganicOutputCategory::where('name', 'Pupuk')->firstOrFail();
        $buyer = Buyer::firstOrFail();
        $vendor = Vendor::firstOrFail();
        $source1 = WasteSource::where('campus_id', $campus1->id)->firstOrFail();

        // Kampus 1: Timbang 100 kg (40 kardus, 40 organik, 20 residu)
        app(WeighingController::class)->store([
            'campus_id' => $campus1->id,
            'waste_source_id' => $source1->id,
            'weigh_date' => now()->format('Y-m-d'),
            'items' => [
                ['waste_type_id' => $kardus->id, 'weight_kg' => 40],
                ['waste_type_id' => $sisaMakanan->id, 'weight_kg' => 40],
                ['waste_type_id' => $residu->id, 'weight_kg' => 20],
            ],
        ]);

        // Terkelola 1: Jual Kardus 30 kg (nilai Rp 54.000 tidak boleh muncul di halaman publik)
        app(SaleController::class)->store([
            'campus_id' => $campus1->id,
            'buyer_id' => $buyer->id,
            'sale_date' => now()->format('Y-m-d'),
            'items' => [
                ['waste_type_id' => $kardus->id, 'weight_kg' => 30, 'price_per_kg' => 1800],
            ],
        ]);

        // Terkelola 2: Olah Organik 30 kg -> Pupuk 14 kg
        app(OrganicProcessingController::class)->store([
            'campus_id' => $campus1->id,
            'process_date' => now()->format('Y-m-d'),
            'organic_output_category_id' => $pupuk->id,
            'output_weight_kg' => 14,
            'items' => [
                ['waste_type_id' => $sisaMakanan->id, 'weight_kg' => 30],
            ],
        ]);

        // Tidak Terkelola: Angkut Residu 20 kg
        app(PickupController::class)->store([
            'campus_id' => $campus1->id,
            'vendor_id' => $vendor->id,
            'pickup_date' => now()->format('Y-m-d'),
            'cost_per_kg' => 150,
            'items' => [
                ['waste_type_id' => $residu->id, 'weight_kg' => 20],
            ],
        ]);

        auth()->logout();
        $this->assertGuest();

        // Total tertangani = 30 + 30 + 20 = 80 kg
        // Terkelola = 60 kg (75,0%), Tidak Terkelola = 20 kg (25,0%)
        $publicAll = $this->get('/');
        $publicAll
            ->assertOk()
            ->assertSee('Sampah Terkelola')
            ->assertSee('Sampah Tidak Terkelola')
            ->assertSee('60,0')
            ->assertSee('75,0%')
            ->assertSee('20,0')
            ->assertSee('25,0%')
            ->assertSee('Pupuk 14,0 kg')
            ->assertDontSee('54.000')
            ->assertDontSee('Rp ');

        if ($campus2->id !== $campus1->id) {
            $publicCampus2 = $this->get('/?campus_id=' . $campus2->id);
            $publicCampus2
                ->assertOk()
                ->assertSee($campus2->name)
                ->assertSee('Belum Ada Data Penanganan Keluar yang Tercatat');
        }
    }
}
