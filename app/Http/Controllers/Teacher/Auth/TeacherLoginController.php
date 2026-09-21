<?php

namespace App\Http\Controllers\Teacher\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeacherLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Teacher/Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::guard('teacher')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('teacher')->user();

            if (!$user->hasRole('teacher')) {
                Auth::guard('teacher')->logout();
                return back()->withErrors([
                    'email' => 'Bu hesab müəllim deyil.',
                ]);
            }

            $request->session()->regenerate();

            if (!$user->teacherProfile?->is_verified) {
                return redirect()->route('teacher.awaiting-verification');
            }

            return redirect()->intended(route('teacher.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Daxil edilən məlumatlar yanlışdır.',
        ])->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('teacher')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('teacher.login');
    }
}
