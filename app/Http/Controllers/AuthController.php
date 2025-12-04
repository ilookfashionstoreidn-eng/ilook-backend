<?php

namespace App\Http\Controllers; 

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

use Tymon\JWTAuth\Facades\JWTAuth; 
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller {
    
    public function register(Request $request)
    {
        \Log::info('Register request received', ['data' => $request->all()]);
    
        try {
            $validatedData = $request->validate([
                'name' => 'required|string',
                'email' => 'required|string|email|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'role' => ['required', Rule::in(Role::pluck('name')->toArray())],
                'id_penjahit' => 'nullable|exists:penjahit_cmt,id_penjahit',
                'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:15000',
            ]);
    
            \Log::info('Validation passed', ['validatedData' => $validatedData]);
    
            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('foto_user', 'public'); 
                \Log::info('📸 Foto berhasil disimpan', ['path' => $fotoPath]);
            }
            // Buat user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'id_penjahit' => $request->role === 'penjahit' ? $request->id_penjahit : null,
                'foto' => $fotoPath, 
            ]);

             // Jika ada gambar Foto, simpan ke storage
           if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('foto_user', 'public'); // Simpan di storage/app/public/ktp_penjahit
            \Log::info('📸 Foto berhasil disimpan', ['path' => $validated['foto']]);
        }
       

    
            \Log::info('User created successfully', ['user_id' => $user->id]);
    
            // Cari role dengan guard api
            $role = Role::where('name', $request->role)->where('guard_name', 'api')->first();
    
            if (!$role) {
                \Log::error('Role not found', ['role' => $request->role]);
                return response()->json(['error' => 'Role not found'], 400);
            }
    
            $user->assignRole($role->name);
            \Log::info('Role assigned successfully', ['role' => $role->name]);
    
            return response()->json(['message' => 'User registered successfully'], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error', ['errors' => $e->errors()]);
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Error during registration', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Something went wrong', 'details' => $e->getMessage()], 500);
        }
    }
    
    

    public function login(Request $request) {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
    
        \Log::info('Login attempt:', $request->only('email', 'password'));
    
        if (!$token = auth()->attempt($request->only('email', 'password'))) {
            \Log::warning('Unauthorized attempt for email: ' . $request->email);
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $user = auth()->user();
        $role = $user->roles->pluck('name')->first();
    
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role, 
                'foto' => $user->foto,
            ],
        ]);
    }

    public function getKasir()
    {
        $kasir = User::role('kasir')->select('id', 'name')->get();
        return response()->json($kasir);
    }

}
