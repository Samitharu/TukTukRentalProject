<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Http\Requests\Admin\StoreUserRequest;
use Modules\Admin\Http\Requests\Admin\UpdateUserRequest;

final class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with('roles')->orderBy('name')->paginate(20);

        return view('admin::admin.users.index', compact('users'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        $roles = $request->user()->assignableRoles();

        return view('admin::admin.users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('status', __('User created.'));
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        $roles = $request->user()->assignableRoles();

        return view('admin::admin.users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('status', __('User updated.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', __('User removed.'));
    }
}
