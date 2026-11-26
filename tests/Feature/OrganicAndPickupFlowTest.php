<?php

namespace Tests\Feature;

use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OrganicProcessingController;
use App\Http\Controllers\PickupController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\WeighingController;
use App\Livewire\Pages\Master\Index as MasterIndex;
use App\Models\Buyer;
use App\Models\Campus;
use App\Models\OrganicOutputCategory;
use App\Models\OrganicProcessing;
use App\Models\Pickup;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteSource;
use App\Models\WasteStockSummary;
use App\Models\WasteType;
use Database\Seeders\CampusSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OrganicAndPickupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Campus $campus;
    protected User $superAdmin;
    protected User $adminKampus;
    protected WasteType $sisaMakanan;
    protected WasteType $sampahTaman;
    protected WasteType $kardus;
    protected WasteType $residuCampur;
    protected Vendor $vendor;
    protected Buyer $buyer;
    protected WasteSource $source;
    protected OrganicOutputCategory $pupukCategory;
    protected OrganicOutputCategory $pakanCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CampusSeeder::class,
            RolePermissionSeeder::class,
            MasterDataSeeder::class,
        ]);

        $this->campus = Campus::firstOrFail();
        $this->superAdmin = User::factory()->create(['campus_id' => null]);
        $this->superAdmin->assignRole('super_admin');

        $this->adminKampus = User::factory()->create(['campus_id' => $this->campus->id]);
        $this->adminKampus->assignRole('admin_kampus');

        $this->sisaMakanan = WasteType::where('name', 'Organik Sisa Makanan')->firstOrFail();
        $this->sampahTaman = WasteType::where('name', 'Sampah Taman')->firstOrFail();
        $this->kardus = WasteType::where('name', 'Kardus')->firstOrFail();
        $this->residuCampur = WasteType::where('name', 'Residu Campur')->firstOrFail();

        $this->vendor = Vendor::firstOrFail();
        $this->buyer = Buyer::firstOrFail();
        $this->source = WasteSource::where('campus_id', $this->campus->id)->firstOrFail();

        $this->pupukCategory = OrganicOutputCategory::where('name', 'Pupuk')->firstOrFail();
        $this->pakanCategory = OrganicOutputCategory::where('name', 'Pakan')->firstOrFail();
    }

    protected function seedWeighing(): int
    {
        $this->actingAs($this->adminKampus);

        $res = app(WeighingController::class)->store([
            'campus_id' => $this->campus->id,
            'waste_source_id' => $this->source->id,
            'weigh_date' => now()->format('Y-m-d'),
            'items' => [
                ['waste_type_id' => $this->sisaMakanan->id, 'weight_kg' => 50],
                ['waste_type_id' => $this->sampahTaman->id, 'weight_kg' => 30],
                ['waste_type_id' => $this->kardus->id, 'weight_kg' => 40],
                ['waste_type_id' => $this->residuCampur->id, 'weight_kg' => 20],
            ],
        ]);

        return $res['session']->id;
    }

    public function test_weighing_separates_sellable_organic_and_residual_pools(): void
    {
        $this->seedWeighing();

        $metrics = WasteStockSummary::getMetrics($this->campus->id);

        $this->assertEquals(140.0, $metrics['total_weighed_kg']);
        $this->assertEquals(40.0, $metrics['sellable_stock_kg']);
        $this->assertEquals(80.0, $metrics['organic_stock_kg']);
        $this->assertEquals(20.0, $metrics['residual_stock_kg']);
        $this->assertEquals(140.0, $metrics['current_stock_kg']);
    }

    public function test_master_data_manages_organic_output_categories_and_blocks_bound_delete(): void
    {
        $this->seedWeighing();

        $this->actingAs($this->superAdmin);

        Livewire::test(MasterIndex::class)
            ->set('activeTab', 'organic_outputs')
            ->set('organicOutputName', 'Biogas Kampus')
            ->call('saveOrganicOutputCategory')
            ->assertHasNoErrors();

        $biogas = OrganicOutputCategory::where('name', 'Biogas Kampus')->first();
        $this->assertNotNull($biogas);
        $this->assertTrue($biogas->is_active);

        // Gunakan kategori Biogas Kampus di pengolahan organik
        $procRes = app(OrganicProcessingController::class)->store([
            'campus_id' => $this->campus->id,
            'process_date' => now()->format('Y-m-d'),
            'organic_output_category_id' => $biogas->id,
            'output_weight_kg' => 12,
            'items' => [
                ['waste_type_id' => $this->sisaMakanan->id, 'weight_kg' => 25],
            ],
        ]);

        // Kategori yang sudah terikat transaksi tidak boleh dihapus
        $delFail = app(MasterDataController::class)->executeDelete('organic_output', $biogas->id);
        $this->assertFalse($delFail['success']);

        // Setelah transaksi dihapus, kategori boleh dihapus
        app(OrganicProcessingController::class)->destroy($procRes['processing']->id);
        $delSuccess = app(MasterDataController::class)->executeDelete('organic_output', $biogas->id);
        $this->assertTrue($delSuccess['success']);
    }

    public function test_pickup_stores_itemized_residual_and_rejects_organic_category(): void
    {
        $this->seedWeighing();

        $pickupController = app(PickupController::class);

        $rejectedOrganic = false;
        try {
            $pickupController->store([
                'campus_id' => $this->campus->id,
                'vendor_id' => $this->vendor->id,
                'pickup_date' => now()->format('Y-m-d'),
                'cost_per_kg' => 150,
                'items' => [
                    ['waste_type_id' => $this->sisaMakanan->id, 'weight_kg' => 10],
                ],
            ]);
        } catch (ValidationException $e) {
            $rejectedOrganic = true;
        }
        $this->assertTrue($rejectedOrganic, 'Organic waste must be rejected from residual pickup');

        $created = $pickupController->store([
            'campus_id' => $this->campus->id,
            'vendor_id' => $this->vendor->id,
            'pickup_date' => now()->format('Y-m-d'),
            'cost_per_kg' => 150,
            'items' => [
                ['waste_type_id' => $this->residuCampur->id, 'weight_kg' => 15],
            ],
        ]);

        /** @var Pickup $pickup */
        $pickup = $created['pickup']->load('items');
        $this->assertEquals(15.0, (float) $pickup->volume_kg);
        $this->assertCount(1, $pickup->items);
        $this->assertEquals($this->residuCampur->id, $pickup->items->first()->waste_type_id);

        $metricsAfterCreate = WasteStockSummary::getMetrics($this->campus->id);
        $this->assertEquals(15.0, $metricsAfterCreate['total_picked_up_kg']);
        $this->assertEquals(5.0, $metricsAfterCreate['residual_stock_kg']);
        $this->assertEquals(80.0, $metricsAfterCreate['organic_stock_kg']);

        $pickupController->update($pickup->id, [
            'campus_id' => $this->campus->id,
            'vendor_id' => $this->vendor->id,
            'pickup_date' => now()->format('Y-m-d'),
            'cost_per_kg' => 200,
            'items' => [
                ['waste_type_id' => $this->residuCampur->id, 'weight_kg' => 18],
            ],
        ]);

        $metricsAfterUpdate = WasteStockSummary::getMetrics($this->campus->id);
        $this->assertEquals(18.0, $metricsAfterUpdate['total_picked_up_kg']);
        $this->assertEquals(2.0, $metricsAfterUpdate['residual_stock_kg']);
        $this->assertEquals(3600.0, $metricsAfterUpdate['total_pickup_cost']);

        $delRes = $pickupController->destroy($pickup->id);
        $this->assertTrue($delRes['success']);

        $metricsAfterDelete = WasteStockSummary::getMetrics($this->campus->id);
        $this->assertEquals(0.0, $metricsAfterDelete['total_picked_up_kg']);
        $this->assertEquals(20.0, $metricsAfterDelete['residual_stock_kg']);
    }

    public function test_organic_processing_tracks_master_output_and_enforces_weighing_lock(): void
    {
        $sessionId = $this->seedWeighing();

        $processingController = app(OrganicProcessingController::class);

        // Residu tidak boleh diolah sebagai organik
        $rejectedResidual = false;
        try {
            $processingController->store([
                'campus_id' => $this->campus->id,
                'process_date' => now()->format('Y-m-d'),
                'organic_output_category_id' => $this->pupukCategory->id,
                'output_weight_kg' => 5,
                'items' => [
                    ['waste_type_id' => $this->residuCampur->id, 'weight_kg' => 5],
                ],
            ]);
        } catch (ValidationException $e) {
            $rejectedResidual = true;
        }
        $this->assertTrue($rejectedResidual);

        // Berat hasil olah tidak boleh 0
        $rejectedZeroOutput = false;
        try {
            $processingController->store([
                'campus_id' => $this->campus->id,
                'process_date' => now()->format('Y-m-d'),
                'organic_output_category_id' => $this->pupukCategory->id,
                'output_weight_kg' => 0,
                'items' => [
                    ['waste_type_id' => $this->sisaMakanan->id, 'weight_kg' => 10],
                ],
            ]);
        } catch (ValidationException $e) {
            $rejectedZeroOutput = true;
        }
        $this->assertTrue($rejectedZeroOutput);

        // Catat pengolahan: 30 kg sisa makanan + 10 kg sampah taman -> Pupuk 18 kg
        $created = $processingController->store([
            'campus_id' => $this->campus->id,
            'process_date' => now()->format('Y-m-d'),
            'organic_output_category_id' => $this->pupukCategory->id,
            'output_weight_kg' => 18,
            'items' => [
                ['waste_type_id' => $this->sisaMakanan->id, 'weight_kg' => 30],
                ['waste_type_id' => $this->sampahTaman->id, 'weight_kg' => 10],
            ],
        ]);

        /** @var OrganicProcessing $proc */
        $proc = $created['processing']->load(['items', 'outputCategory']);
        $this->assertEquals(40.0, (float) $proc->input_weight_kg);
        $this->assertEquals($this->pupukCategory->id, $proc->organic_output_category_id);
        $this->assertEquals(18.0, (float) $proc->output_weight_kg);
        $this->assertCount(2, $proc->items);

        $metrics = WasteStockSummary::getMetrics($this->campus->id);
        $this->assertEquals(40.0, $metrics['total_processed_kg']);
        $this->assertEquals(18.0, $metrics['total_processed_output_kg']);
        $this->assertEquals(40.0, $metrics['organic_stock_kg']);

        // Strict delete pada sesi timbang yang sudah diolah harus ditolak
        $weighingDeleteBlocked = app(WeighingController::class)->destroy($sessionId);
        $this->assertFalse($weighingDeleteBlocked['success']);
        $this->assertStringContainsString('Pengolahan Organik', $weighingDeleteBlocked['message']);

        // Update menjadi Pakan 15 kg dari total awal 30 kg
        $processingController->update($proc->id, [
            'campus_id' => $this->campus->id,
            'process_date' => now()->format('Y-m-d'),
            'organic_output_category_id' => $this->pakanCategory->id,
            'output_weight_kg' => 15,
            'items' => [
                ['waste_type_id' => $this->sisaMakanan->id, 'weight_kg' => 20],
                ['waste_type_id' => $this->sampahTaman->id, 'weight_kg' => 10],
            ],
        ]);

        $metricsUpdated = WasteStockSummary::getMetrics($this->campus->id);
        $this->assertEquals(30.0, $metricsUpdated['total_processed_kg']);
        $this->assertEquals(15.0, $metricsUpdated['total_processed_output_kg']);
        $this->assertEquals(50.0, $metricsUpdated['organic_stock_kg']);

        $delProc = $processingController->destroy($proc->id);
        $this->assertTrue($delProc['success']);
        $this->assertEquals(80.0, WasteStockSummary::getMetrics($this->campus->id)['organic_stock_kg']);

        $weighingDeleteAllowed = app(WeighingController::class)->destroy($sessionId);
        $this->assertTrue($weighingDeleteAllowed['success']);
    }

    public function test_stock_resync_and_operational_pages_stay_consistent(): void
    {
        $this->seedWeighing();

        app(SaleController::class)->store([
            'campus_id' => $this->campus->id,
            'buyer_id' => $this->buyer->id,
            'sale_date' => now()->format('Y-m-d'),
            'items' => [
                ['waste_type_id' => $this->kardus->id, 'weight_kg' => 25, 'price_per_kg' => 1800],
            ],
        ]);

        app(OrganicProcessingController::class)->store([
            'campus_id' => $this->campus->id,
            'process_date' => now()->format('Y-m-d'),
            'organic_output_category_id' => $this->pupukCategory->id,
            'output_weight_kg' => 14,
            'items' => [
                ['waste_type_id' => $this->sisaMakanan->id, 'weight_kg' => 30],
            ],
        ]);

        app(PickupController::class)->store([
            'campus_id' => $this->campus->id,
            'vendor_id' => $this->vendor->id,
            'pickup_date' => now()->format('Y-m-d'),
            'cost_per_kg' => 150,
            'items' => [
                ['waste_type_id' => $this->residuCampur->id, 'weight_kg' => 15],
            ],
        ]);

        WasteStockSummary::recalculateForCampus($this->campus->id);
        $synced = WasteStockSummary::getMetrics($this->campus->id);

        $this->assertEquals(140.0, $synced['total_weighed_kg']);
        $this->assertEquals(25.0, $synced['total_sold_kg']);
        $this->assertEquals(30.0, $synced['total_processed_kg']);
        $this->assertEquals(14.0, $synced['total_processed_output_kg']);
        $this->assertEquals(15.0, $synced['total_picked_up_kg']);
        $this->assertEquals(15.0, $synced['sellable_stock_kg']);
        $this->assertEquals(50.0, $synced['organic_stock_kg']);
        $this->assertEquals(5.0, $synced['residual_stock_kg']);
        $this->assertEquals(70.0, $synced['current_stock_kg']);

        $this->get('/processing')->assertOk()->assertSee('Pengolahan Organik')->assertSee('Pupuk')->assertSee('14,0');
        $this->get('/pickups')->assertOk()->assertSee('Residu Campur');
        $this->get('/stock')->assertOk()->assertSee('Olah Organik')->assertSee('Residu TPA');
    }
}
