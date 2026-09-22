<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bankın cavabı. Sessiya yoxdur — ödəniş referensi ilə tapılır, imza yoxlanılır.
 *
 * Eyni callback iki dəfə gəlsə nəticə dəyişmir (PaymentProcessor idempotentdir).
 */
class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly PaymentProcessor $payments,
    ) {
    }

    public function __invoke(Request $request, string $provider): RedirectResponse
    {
        $gateway = $this->gateways->make($provider);

        abort_unless($gateway->verifyCallback($request), 403, 'Ödəniş imzası uyğun gəlmir.');

        $result = $gateway->parseCallback($request);
        $payment = $this->findPayment($result->reference, $gateway->name());

        abort_if($payment === null, 404, 'Ödəniş tapılmadı.');

        ['payment' => $payment] = $this->payments->handleCallback($payment, $result);

        $exam = $payment->purchasable;
        $target = $exam instanceof Exam
            ? $exam->publicUrl()
            : route('student.exams.index');

        return redirect()->to($target)->with(
            $payment->isPaid() ? 'success' : 'error',
            $payment->isPaid()
                ? 'Ödəniş tamamlandı, imtahan açıldı.'
                : 'Ödəniş uğursuz oldu. Yenidən cəhd edə bilərsiniz.'
        );
    }

    /** Referens bankın əməliyyat nömrəsi, ya da bizim ödənişin ID-sidir. */
    private function findPayment(string $reference, string $provider): ?Payment
    {
        if ($reference === '') {
            return null;
        }

        $payment = Payment::where('provider', $provider)
            ->where('provider_ref', $reference)
            ->first();

        if ($payment) {
            return $payment;
        }

        return ctype_digit($reference)
            ? Payment::where('provider', $provider)->find((int) $reference)
            : null;
    }
}
