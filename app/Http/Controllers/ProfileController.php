<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $recentActivities = ActivityLog::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        return view('profile.index', compact('user', 'recentActivities'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        ActivityLog::record('profile_updated', 'User updated account profile information.', $user);

        return back()->with('success', 'Profile information updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'The new password must be different from your current password.',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        ActivityLog::record('password_changed', 'User successfully changed their account password.', $user);

        return back()->with('success', 'Password updated successfully.');
    }

    public function logoutOtherDevices(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        Auth::logoutOtherDevices($request->password);

        ActivityLog::record('session_logout_other_devices', 'User logged out of all other active browser sessions.');

        return back()->with('success', 'Successfully logged out of all other active browser sessions.');
    }
}
