<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Ödəniş provayderi. Bank qoşulanda yalnız bu interfeysin yeni realizasiyası yazılır.
 */
interface PaymentGateway
{
    /** payments.provider sütununda saxlanılan ad */
    public function name(): string;

    /** Ödəniş üçün istifadəçinin yönləndiriləcəyi ünvan (bankın səhifəsi) */
    public function redirectUrl(Payment $payment): string;

    /**
     * Callback həqiqətən bankdandırmı: imza/hash yoxlanışı.
     * Yanlış imzalı sorğu emal edilmir.
     */
    public function verifyCallback(Request $request): bool;

    /** Callback-dan ödənişin referensini və nəticəsini oxuyur */
    public function parseCallback(Request $request): CallbackResult;
}
