<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use DataTables;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.index', only: ['index']),
            new Middleware('permission:users.edit', only: ['edit', 'update']),
        ];
    }

    public function index()
    {
        if (!request()->ajax()) {
            return view('user.index');
        }

        $query = User::query();

        $canEdit = auth()->user()->can('users.edit');

        return DataTables::eloquent($query)
            ->addColumn('urls', fn(User $user) => [
                'edit' => $canEdit ? route('users.edit', $user) : null,
            ])
            ->make(true);
    }

    public function edit(User $user)
    {
        $user->load(['roles', 'permissions']);

        return view('user.edit', [
            'user' => $user,
            'roles' => Role::with('permissions')->get(),
            'permissions' => Permission::get(),
        ]);
    }

    public function update(User $user, Request $request)
    {
        // TODO: realizar validación del request
        $updatePassword = $request->password
            ? ['password' => $request->password]
            : [];

        $role_ids = array_map('intval', $request->role_ids ?? []);
        $permission_ids = array_map('intval', $request->permission_ids ?? []);

        $user->syncRoles($role_ids);
        $user->syncPermissions($permission_ids);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ] + $updatePassword);

        return to_route('users.index')->with('success', 'Usuario actualizado');
    }

}
