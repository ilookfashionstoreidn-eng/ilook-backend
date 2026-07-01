<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->first(),
                'menus' => $user->menus ?? [],
                'gudang_access' => $user->gudang_access ?? [],
                'foto' => $user->foto,
                'id_penjahit' => $user->id_penjahit,
            ];
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(Role::pluck('name')->toArray())],
            'id_penjahit' => 'nullable|exists:penjahit_cmt,id_penjahit',
            'menus' => 'nullable|array',
            'gudang_access' => 'nullable|array',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'id_penjahit' => $request->role === 'penjahit' ? $request->id_penjahit : null,
            'menus' => $request->menus ?? [],
            'gudang_access' => $request->gudang_access ?? [],
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
                'menus' => $user->menus,
                'gudang_access' => $user->gudang_access,
            ]
        ], 201);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->pluck('name')->first(),
            'menus' => $user->menus ?? [],
            'foto' => $user->foto,
            'id_penjahit' => $user->id_penjahit,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['required', Rule::in(Role::pluck('name')->toArray())],
            'id_penjahit' => 'nullable|exists:penjahit_cmt,id_penjahit',
            'menus' => 'nullable|array',
            'gudang_access' => 'nullable|array',
        ];

        $validatedData = $request->validate($rules);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'id_penjahit' => $request->role === 'penjahit' ? $request->id_penjahit : null,
            'menus' => $request->menus ?? [],
            'gudang_access' => $request->gudang_access ?? [],
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        $user->syncRoles([$request->role]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
                'menus' => $user->menus,
                'gudang_access' => $user->gudang_access,
            ]
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->id === auth()->user()->id) {
            return response()->json(['error' => 'You cannot delete yourself!'], 400);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}
