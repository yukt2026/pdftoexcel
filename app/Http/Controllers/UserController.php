<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate(15)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'phone' => ['nullable', 'string', 'max:50'],
        ], [
            'role.in' => 'Selected role must be either Admin or User.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => 'active',
            'phone' => $validated['phone'] ?? null,
        ]);

        ActivityLog::record('user_created', "Administrator created user account for {$user->email} with role '{$user->role}'.", $user);

        return back()->with('success', "User account for {$user->name} ({$user->role}) created successfully.");
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'status' => ['required', 'in:active,inactive'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // Prevent self-demotion or self-deactivation
        if (auth()->id() === $user->id) {
            if ($validated['role'] !== User::ROLE_ADMIN) {
                return back()->withErrors(['role' => 'You cannot remove your own Administrator role.']);
            }
            if ($validated['status'] !== 'active') {
                return back()->withErrors(['status' => 'You cannot deactivate your own account.']);
            }
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'phone' => $validated['phone'] ?? null,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        ActivityLog::record('user_updated', "Updated profile/role for {$user->email} (Role: {$user->role}, Status: {$user->status})", $user);

        return back()->with('success', "User '{$user->name}' updated successfully.");
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $email = $user->email;
        $user->delete();

        ActivityLog::record('user_deleted', "Deleted user account {$email}");

        return back()->with('success', "User {$email} has been deleted.");
    }
}
