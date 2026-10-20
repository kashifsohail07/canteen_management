<?php

namespace Tests\Feature\Canteen;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_login_page_is_shown_to_guests(): void
    {
        $this->get('/login')->assertOk()->assertSee('Canteen Hisab');
    }

    public function test_admin_can_login_with_default_credentials(): void
    {
        $this->post('/login', ['email' => 'admin@canteen.local', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_staff_can_login(): void
    {
        $this->post('/login', ['email' => 'staff@canteen.local', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'admin@canteen.local', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'gone@canteen.local', 'is_active' => false]);

        $this->post('/login', ['email' => 'gone@canteen.local', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->admin)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login_from_protected_pages(): void
    {
        foreach (['/', '/vendors', '/sales/create', '/purchases/create', '/payments/create', '/closing', '/settings', '/owner'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->post('/sales', ['amount' => 100])->assertRedirect(route('login'));
    }

    public function test_logged_in_users_are_redirected_away_from_login(): void
    {
        $this->actingAs($this->staff)->get('/login')->assertRedirect();
    }

    public function test_database_seeder_creates_the_documented_default_logins(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->post('/login', ['email' => 'admin@canteen.local', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'staff@canteen.local', 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->assertSame(4, \App\Models\Vendor::count());
    }
}
