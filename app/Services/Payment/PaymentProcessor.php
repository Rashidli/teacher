<?php

namespace App\Services\Payment;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ödənişin yaradılması və bank cavabının emalı.
 *
 * Callback idempotentdir: eyni cavab iki dəfə gəlsə ödəniş bir dəfə "paid" olur və giriş
 * iki dəfə yaradılmır. Ödənişin paid olması ilə girişin açılması eyni tranzaksiyadadır —
 * biri baş tutub digəri baş tutmaya bilməz.
 */
class PaymentProcessor
{
    public function __construct(private readonly ExamAccessService $access)
    {
    }

    /**
     * Gözləyən ödəniş yaradır. Məbləğ alış anındakı qiymətdir: imtahanın qiyməti sonra
     * dəyişsə də ödəniş tarixçəsi düz qalır.
     */
    public function startExamPurchase(User $user, Exam $exam, PaymentGateway $gateway): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'purchasable_type' => $exam->getMorphClass(),
            'purchasable_id' => $exam->id,
            'amount' => $exam->price,
            'currency' => config('payments.currency', 'AZN'),
            'status' => Payment::STATUS_PENDING,
            'provider' => $gateway->name(),
        ]);
    }

    /**
     * Bank cavabını emal edir.
     *
     * @return array{payment: Payment, access: ?ExamAccess}
     */
    public function handleCallback(Payment $payment, CallbackResult $result): array
    {
        return DB::transaction(function () use ($payment, $result) {
            // Paralel gələn iki callback-dan yalnız biri statusu dəyişsin
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $payload = PaymentPayloadSanitizer::clean($result->payload);

            // Təkrar callback: status artıq son vəziyyətdədir, heç nə dəyişmir
            if (! $payment->isPending()) {
                return [
                    'payment' => $payment,
                    'access' => $payment->isPaid() ? $this->existingAccess($payment) : null,
                ];
            }

            if (! $result->successful) {
                $payment->transitionTo(Payment::STATUS_FAILED, [
                    'payload' => $payload,
                    'provider_ref' => $payment->provider_ref ?? $result->reference,
                ]);

                return ['payment' => $payment, 'access' => null];
            }

            $payment->transitionTo(Payment::STATUS_PAID, [
                'payload' => $payload,
                'provider_ref' => $payment->provider_ref ?? $result->reference,
            ]);

            return [
                'payment' => $payment,
                'access' => $this->access->grantFromPayment($payment->fresh()),
            ];
        });
    }

    /** Ödəniş geri qaytarılır və giriş bağlanır. */
    public function refund(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->canTransitionTo(Payment::STATUS_REFUNDED)) {
                $payment->transitionTo(Payment::STATUS_REFUNDED);
                $this->access->revokeForPayment($payment);
            }

            return $payment;
        });
    }

    private function existingAccess(Payment $payment): ?ExamAccess
    {
        return ExamAccess::where('payment_id', $payment->id)->first();
    }
}
