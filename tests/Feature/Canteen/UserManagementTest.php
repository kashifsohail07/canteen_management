<?php

namespace Tests\Feature\Canteen;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
        $this->super = User::factory()->superUser()->create([
            'email' => 'super@canteen.local',
            'name' => 'Super User',
        ]);
    }

    public function test_super_user_can_list_and_create_users(): void
    {
        $this->actingAs($this->super)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Users')
            ->assertSee(route('users.create'), false);

        $this->actingAs($this->super)
            ->post(route('users.store'), [
                'name' => 'New Staff',
                'email' => 'staff2@canteen.local',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'staff',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $created = User::where('email', 'staff2@canteen.local')->sole();
        $this->assertSame('New Staff', $created->name);
        $this->assertSame(UserRole::Staff, $created->role);
        $this->assertTrue($created->is_active);
        $this->assertNotNull(AuditLog::where('action', 'user.created')->where('auditable_id', $created->id)->first());
    }

    public function test_admin_and_staff_cannot_access_user_management(): void
    {
        $this->actingAs($this->admin)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'X', 'email' => 'x@canteen.local', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'staff',
        ])->assertForbidden();
    }

    public function test_super_user_can_update_user_and_password(): void
    {
        $target = User::factory()->staff()->create(['email' => 'old@canteen.local']);

        $this->actingAs($this->super)
            ->put(route('users.update', $target), [
                'name' => 'Updated Name',
                'email' => 'new@canteen.local',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
                'role' => 'admin',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('new@canteen.local', $target->email);
        $this->assertSame(UserRole::Admin, $target->role);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass123', $target->password));
    }

    public function test_super_user_cannot_deactivate_self_or_last_super(): void
    {
        $this->actingAs($this->super)
            ->put(route('users.update', $this->super), [
                'name' => $this->super->name,
                'email' => $this->super->email,
                'role' => 'staff',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($this->super)
            ->post(route('users.toggle-active', $this->super))
            ->assertRedirect();

        $this->assertTrue($this->super->fresh()->is_active);
    }

    public function test_super_user_can_deactivate_other_user(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($this->super)
            ->post(route('users.toggle-active', $staff))
            ->assertRedirect();

        $this->assertFalse($staff->fresh()->is_active);
    }

    public function test_menu_shows_users_link_only_for_super_user(): void
    {
        $this->actingAs($this->super)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('users.index'), false);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('users.index'), false);
    }
}
