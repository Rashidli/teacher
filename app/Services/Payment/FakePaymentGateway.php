<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Sınaq provayderi — REAL PUL HƏRƏKƏT ETMİR.
 *
 * "Al" basılanda bank səhifəsi açılmır: ödəniş `confirm()` ilə dərhal uğurlu sayılır,
 * `PaymentProcessor` onu "paid" edir və girişi açır. Axının qalan hissəsi (ödəniş qeydi,
 * status keçidi, giriş hüququ) real provayderdəki kimidir — bank qoşulanda yalnız yeni
 * driver yazılacaq.
 *
 * Uğursuz axını əl ilə yoxlamaq üçün sınaq "bank səhifəsi" (`/payments/fake/{payment}`)
 * saxlanılıb: orada "Uğursuz" seçilir və REAL callback route-u imza ilə çağırılır.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function redirectUrl(Payment $payment): string
    {
        return route('payments.fake.show', $payment);
    }

    /**
     * Test rejimində ödənişin dərhal təsdiqi.
     *
     * Bank cavabı ilə eyni formadadır (`CallbackResult`), ona görə `PaymentProcessor`
     * üçün ayrıca kod yolu yoxdur: status keçidi və giriş hüququ eyni tranzaksiyadan keçir.
     */
    public function confirm(Payment $payment): CallbackResult
    {
        return new CallbackResult(
            reference: (string) $payment->id,
            successful: true,
            payload: ['test_mode' => true, 'confirmed_at' => now()->toAtomString()],
        );
    }

    public function verifyCallback(Request $request): bool
    {
        $reference = (string) $request->input('reference');
        $status = (string) $request->input('status');

        return hash_equals(
            self::signature($reference, $status),
            (string) $request->input('signature')
        );
    }

    public function parseCallback(Request $request): CallbackResult
    {
        return new CallbackResult(
            reference: (string) $request->input('reference'),
            successful: $request->input('status') === 'success',
            payload: $request->except(['signature', '_token']),
        );
    }

    /** Fake "bank" səhifəsi düymələri üçün imza */
    public static function signature(string $reference, string $status): string
    {
        return hash_hmac('sha256', $reference.'|'.$status, (string) config('app.key'));
    }
}
