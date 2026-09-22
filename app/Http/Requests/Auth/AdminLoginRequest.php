<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin girişi: eyni "web" guard, amma yalnız admin rolu buraxılır.
 * Ehtimal seçmə cəhdlərinə qarşı email+IP üzrə sürət limiti var.
 *
 * Admin olmayan hesab səhv parolla EYNİ mesajı alır: fərqli mesaj parolun
 * düz olduğunu açardı (hansı hesabın parolunu tapdığını bildirərdi).
 */
class AdminLoginRequest extends FormRequest
{
    /** Bir email + IP üçün icazə verilən uğursuz cəhd sayı */
    public const MAX_ATTEMPTS = 5;

    /** Uğursuz girişin yeganə mesajı — səbəb (parol, yoxsa rol) açıqlanmır */
    public const FAILED_MESSAGE = 'Daxil edilən məlumatlar yanlışdır.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->fail();
        }

        // Parol düz olsa da, admin olmayan hesab admin panelinə buraxılmır:
        // sessiya açıq qalmasın deyə dərhal çıxarılır.
        if (! $this->user()->hasRole('admin')) {
            Auth::logout();
            $this->session()->invalidate();
            $this->session()->regenerateToken();

            $this->fail();
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function fail(): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => self::FAILED_MESSAGE,
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return 'admin|'.Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
