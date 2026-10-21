<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $old = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        $user->name = $data['name'];
        $user->email = $data['email'];

        $passwordChanged = ! empty($data['password']);
        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        $user->save();

        $this->audit->log(
            'profile.updated',
            $user,
            $old,
            [
                'name' => $user->name,
                'email' => $user->email,
                'password_changed' => $passwordChanged,
            ],
            null,
            $user,
        );

        return redirect()->route('profile.edit')->with('success', 'Profile save ho gaya.');
    }
}
