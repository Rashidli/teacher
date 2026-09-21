<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Sınaq provayderi: real bank əvəzinə öz "bank səhifəmizə" yönləndirir, orada
 * "Uğurlu"/"Uğursuz" seçilir və REAL callback route-u çağırılır.
 *
 * Beləliklə bütün axın (pending → callback → paid → giriş açılır) indidən real şəkildə
 * işləyir və bank qoşulanda yalnız yeni driver yazılacaq.
 *
 * İmza yoxlanışı da real provayderdəki kimidir: fake səhifə imzanı göndərir, callback isə
 * yoxlayır — yəni interfeysin bu hissəsi də sınaqdan keçir.
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
