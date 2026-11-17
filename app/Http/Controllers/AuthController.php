<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && !$user->isActive()) {
            return back()->withErrors([
                'email' => 'Your account is deactivated. Please contact an administrator.',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            ActivityLog::record('user_login', 'User logged in successfully.');
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            ActivityLog::record('user_logout', 'User logged out.');
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been successfully logged out.');
    }
}
