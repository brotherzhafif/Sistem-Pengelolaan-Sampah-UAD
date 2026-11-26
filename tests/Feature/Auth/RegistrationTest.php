<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\UserController;
use App\Livewire\Pages\Users\Index as UsersIndex;
use App\Models\Campus;
use App\Models\User;
use Database\Seeders\CampusSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_route_is_disabled(): void
    {
        $this->assertFalse(Route::has('register'));

        $this->get('/register')->assertNotFound();
    }

    public function test_login_and_welcome_pages_do_not_expose_registration_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Mulai Sekarang')
            ->assertDontSee(url('/register'));

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Daftar sekarang')
            ->assertDontSee(url('/register'));
    }

    public function test_admin_can_create_user_who_can_authenticate(): void
    {
        $this->seed([CampusSeeder::class, RolePermissionSeeder::class]);

        $campus = Campus::firstOrFail();

        $admin = User::factory()->create(['campus_id' => null]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        Livewire::test(UsersIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Petugas Baru UAD')
            ->set('email', 'petugas.baru@uad.ac.id')
            ->set('password', 'rahasia123')
            ->set('selectedRole', 'petugas_tps')
            ->set('selectedCampusId', $campus->id)
            ->call('saveUser')
            ->assertHasNoErrors();

        $created = User::where('email', 'petugas.baru@uad.ac.id')->first();
        $this->assertNotNull($created);
        $this->assertNotNull($created->email_verified_at);
        $this->assertSame($campus->id, $created->campus_id);
        $this->assertTrue($created->hasRole('petugas_tps'));

        Auth::logout();
        $this->assertGuest();

        $loginComponent = Volt::test('pages.auth.login')
            ->set('form.email', 'petugas.baru@uad.ac.id')
            ->set('form.password', 'rahasia123');

        $loginComponent->call('login');

        $loginComponent
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($created);
    }

    public function test_non_admin_cannot_access_user_management_or_create_user(): void
    {
        $this->seed([CampusSeeder::class, RolePermissionSeeder::class]);

        $campus = Campus::firstOrFail();
        $operator = User::factory()->create(['campus_id' => $campus->id]);
        $operator->assignRole('petugas_tps');

        $this->actingAs($operator);

        $this->get('/users')->assertForbidden();

        $this->expectException(HttpException::class);
        app(UserController::class)->saveUser([
            'name' => 'Unauthorized',
            'email' => 'unauth@uad.ac.id',
            'password' => 'secret123',
            'selectedRole' => 'petugas_tps',
            'selectedCampusId' => $campus->id,
        ]);
    }
}
