<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $role = 'Administrador'): User
    {
        $u = User::factory()->create(['password' => 'LongPassword123!']);
        $u->role_id = Role::where('name', $role)->value('id');
        $u->save();

        return $u;
    }

    public function test_guests_redirect_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Bienvenido de nuevo');
    }

    public function test_successful_login_and_logout(): void
    {
        $u = $this->user();
        $this->post('/login', ['email' => $u->email, 'password' => 'LongPassword123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_bad_password_is_rejected(): void
    {
        $u = $this->user();
        $this->post('/login', ['email' => $u->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_accounts_cannot_login(): void
    {
        $u = $this->user();
        $u->active = false;
        $u->save();
        $this->post('/login', ['email' => $u->email, 'password' => 'LongPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dashboard_reads_database_and_hides_financials_from_warehouse(): void
    {
        $u = $this->user('Bodeguero');
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('No hay alertas de stock.')->assertDontSee('Ganancia bruta estimada');
    }

    public function test_admin_sees_financial_dashboard(): void
    {
        $this->actingAs($this->user())->get('/dashboard')->assertOk()->assertSee('Ganancia bruta estimada');
    }

    public function test_profile_cannot_escalate_role(): void
    {
        $u = $this->user('Vendedor');
        $role = $u->role_id;
        $this->actingAs($u)->put('/profile', ['name' => 'Nuevo nombre', 'email' => $u->email, 'role_id' => Role::where('name', 'Administrador')->value('id')])->assertSessionHasNoErrors();
        $this->assertSame($role, $u->fresh()->role_id);
    }

    public function test_password_change_requires_current_password(): void
    {
        $u = $this->user();
        $this->actingAs($u)->put('/profile', ['name' => $u->name, 'email' => $u->email, 'password' => 'AnotherPassword123!', 'password_confirmation' => 'AnotherPassword123!', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    }

    public function test_password_reset_notification_uses_official_broker(): void
    {
        Notification::fake();
        $u = $this->user();
        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($u, ResetPassword::class);
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
