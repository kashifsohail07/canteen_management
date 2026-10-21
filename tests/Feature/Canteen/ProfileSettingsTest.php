<?php

namespace Tests\Feature\Canteen;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_any_user_can_view_profile_with_roles_guide(): void
    {
        $this->actingAs($this->staff)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee('Roles kya hain?')
            ->assertSee('Super User')
            ->assertSee('Admin')
            ->assertSee('Staff')
            ->assertSee('Aapka role');
    }

    public function test_user_can_update_profile_with_current_password(): void
    {
        $user = User::factory()->staff()->create([
            'email' => 'me@canteen.local',
            'password' => 'oldpass123',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated Me',
                'email' => 'me2@canteen.local',
                'current_password' => 'oldpass123',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Updated Me', $user->name);
        $this->assertSame('me2@canteen.local', $user->email);
        $this->assertTrue(Hash::check('newpass123', $user->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->actingAs($this->staff)
            ->put(route('profile.update'), [
                'name' => $this->staff->name,
                'email' => $this->staff->email,
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');
    }
}
