<?php

namespace App\Http\Controllers\Student\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StudentLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Student/Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::guard('student')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('student')->user();

            if (!$user->hasRole('student')) {
                Auth::guard('student')->logout();
                return back()->withErrors([
                    'email' => 'Bu hesab şagird deyil.',
                ]);
            }

            $request->session()->regenerate();
            return redirect()->intended(route('student.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Daxil edilən məlumatlar yanlışdır.',
        ])->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('student.login');
    }
}
