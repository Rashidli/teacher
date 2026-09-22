<?php

namespace App\Http\Controllers\Teacher\Auth;

use App\Http\Controllers\Controller;
use App\Support\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Müəllim üçün ayrıca giriş səhifəsi (modul açıq olanda). Guard vahiddir ("web"),
 * burada yalnız müəllim rolu tələb olunur.
 */
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Daxil edilən məlumatlar yanlışdır.',
            ])->onlyInput('email');
        }

        $user = $request->user();

        if (! $user->hasRole('teacher')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Bu hesab müəllim deyil.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(Panel::homeUrl($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('teacher.login');
    }
}
