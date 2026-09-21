<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
{
    /** Azərbaycan mobil operator kodları */
    public const OPERATOR_CODES = ['10', '50', '51', '55', '60', '70', '77', '99'];

    /** Bazada saxlanılan format: +994XXXXXXXXX */
    public const PHONE_REGEX = '/^\+994(10|50|51|55|60|70|77|99)\d{7}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Frontend-ə güvənmirik: nömrədəki hərf, boşluq, mötərizə və s. silinir,
     * "+994", "994" və ya yerli "0" prefiksi atılır, sonra +994 əlavə olunur.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => static::normalizePhone($this->input('phone')),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
        ]);
    }

    public static function normalizePhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        // Ardıcıl: əvvəl ölkə kodu, sonra yerli "0" ("+994 (055) 123-45-67" → "551234567")
        if (str_starts_with($digits, '994') && strlen($digits) >= 12) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = substr($digits, 1);
        }

        return '+994'.$digits;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'regex:'.static::PHONE_REGEX, 'unique:'.User::class.',phone'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // Tədris sektoru: hansı dildə oxuduğu (interfeys dilindən ayrıdır)
            'sector' => ['required', Rule::in(Sector::ALL)],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('auth_pages.register.phone_invalid'),
            'phone.unique' => __('auth_pages.register.phone_taken'),
            'sector.required' => __('auth_pages.register.sector_required'),
            'sector.in' => __('auth_pages.register.sector_required'),
            'terms.accepted' => __('auth_pages.register.terms_required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => __('auth_pages.fields.first_name'),
            'last_name' => __('auth_pages.fields.last_name'),
            'email' => __('auth_pages.fields.email'),
            'phone' => __('auth_pages.fields.phone'),
            'password' => __('auth_pages.fields.password'),
        ];
    }
}
