<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Check if current user is admin
     */
    private function ensureAdmin()
    {
        if (auth()->user()->user_type !== 'admin') {
            abort(403, 'Unauthorized action. Only admins can access this resource.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->ensureAdmin();
        $users = User::paginate(15);
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();
        
        $request->validate([
            'username' => 'required|string|unique:users',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'user_type' => 'required|in:mahasiswa,dosen,tendik,admin,calon_mhs',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'user_type' => $request->user_type,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'is_verified' => $request->has('is_verified') ? $request->is_verified : false,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->ensureAdmin();
        return response()->json(['data' => $user]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->ensureAdmin();

        $request->validate([
            'username' => 'sometimes|string|unique:users,username,'.$user->id,
            'email' => 'sometimes|string|email|unique:users,email,'.$user->id,
            'password' => 'sometimes|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'user_type' => 'sometimes|in:mahasiswa,dosen,tendik,admin,calon_mhs',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
        ]);

        $data = $request->only(['username', 'email', 'phone', 'user_type', 'is_active', 'is_verified']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->ensureAdmin();
        $user->delete();
        
        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Toggle the active status of the user.
     */
    public function toggleStatus(Request $request, $id)
    {
        $this->ensureAdmin();
        
        $user = User::findOrFail($id);
        
        $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $user->update(['is_active' => $request->is_active]);

        $statusStr = $request->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'status' => 'success',
            'message' => "User berhasil {$statusStr}.",
            'data' => $user
        ]);
    }
}
