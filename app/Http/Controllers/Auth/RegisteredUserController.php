<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Şagird qeydiyyatı. Bütün yeni hesablar "student" rolunu alır.
 * Müəllim qeydiyyatı ayrıca teacher modulundadır (features.teachers).
 */
class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'operatorCodes' => RegisterRequest::OPERATOR_CODES,
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            // Qeydiyyat hansı dildə olubsa, email-lər də o dildə gedəcək
            'locale' => app()->getLocale(),
        ]);

        $user->assignRole('student');

        event(new Registered($user));

        Auth::guard('student')->login($user);

        return redirect(route('student.dashboard', absolute: false));
    }
}
