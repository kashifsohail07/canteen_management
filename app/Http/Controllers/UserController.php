<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all');

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($filter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(in_array($filter, ['super_user', 'admin', 'staff'], true), fn ($query) => $query->where('role', $filter))
            ->orderByRaw("CASE role WHEN 'super_user' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'q' => $q,
            'filter' => $filter,
            'counts' => [
                'all' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['roles' => UserRole::cases()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        $this->audit->log(
            'user.created',
            $user,
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value, 'is_active' => $user->is_active],
            null,
            $request->user(),
        );

        return redirect()->route('users.index')->with('success', $user->name.' add ho gaya.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
            'isSelf' => $user->is(request()->user()),
            'lastSuper' => $this->isLastActiveSuper($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $isSelf = $user->is($actor);

        $newRole = UserRole::from($data['role']);
        $newActive = (bool) ($data['is_active'] ?? false);

        if ($isSelf && $newRole !== UserRole::SuperUser) {
            throw ValidationException::withMessages([
                'role' => 'Apna role Super User se nahi hata sakte.',
            ]);
        }

        if ($isSelf && ! $newActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Apne aap ko inactive nahi kar sakte.',
            ]);
        }

        if ($this->isLastActiveSuper($user) && ($newRole !== UserRole::SuperUser || ! $newActive)) {
            throw ValidationException::withMessages([
                'role' => 'Last active Super User ko change / inactive nahi kar sakte.',
            ]);
        }

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $newRole;
        $user->is_active = $newActive;

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        $new = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
            'password_changed' => ! empty($data['password']),
        ];

        if ($old !== array_intersect_key($new, $old) || ! empty($data['password'])) {
            $this->audit->log('user.updated', $user, $old, $new, null, $actor);
        }

        return redirect()->route('users.index')->with('success', $user->name.' update ho gaya.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor?->isSuperUser()) {
            abort(403);
        }

        if ($user->is($actor)) {
            return back()->with('error', 'Apne aap ko inactive nahi kar sakte.');
        }

        if ($user->is_active && $this->isLastActiveSuper($user)) {
            return back()->with('error', 'Last active Super User ko inactive nahi kar sakte.');
        }

        $old = $user->is_active;
        $user->is_active = ! $user->is_active;
        $user->save();

        $this->audit->log(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            ['is_active' => $old],
            ['is_active' => $user->is_active],
            null,
            $actor,
        );

        $msg = $user->is_active
            ? $user->name.' active ho gaya.'
            : $user->name.' inactive ho gaya.';

        return back()->with('success', $msg);
    }

    private function isLastActiveSuper(User $user): bool
    {
        if (! $user->isSuperUser() || ! $user->is_active) {
            return false;
        }

        return User::query()
            ->where('role', UserRole::SuperUser)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }
}
