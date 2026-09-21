<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\FakePaymentGateway;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sınaq "bank səhifəsi": real bank əvəzinə burada "Uğurlu"/"Uğursuz" seçilir və REAL
 * callback route-u çağırılır. Route-lar yalnız fake driver seçiləndə və produksiyadan
 * kənarda qeydiyyatdan keçir (routes/web.php).
 */
class FakeGatewayController extends Controller
{
    public function show(Payment $payment): Response
    {
        abort_unless($payment->user_id === auth('student')->id(), 403);
        abort_unless($payment->isPending(), 410, 'Bu ödəniş artıq tamamlanıb.');

        $reference = (string) $payment->id;

        return Inertia::render('Payments/FakeGateway', [
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'title' => $payment->purchasable?->title,
            ],
            'callbackUrl' => route('payments.callback', 'fake'),
            'reference' => $reference,
            'signatures' => [
                'success' => FakePaymentGateway::signature($reference, 'success'),
                'failed' => FakePaymentGateway::signature($reference, 'failed'),
            ],
        ]);
    }
}
