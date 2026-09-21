<?php

namespace App\Services\Payment;

use RuntimeException;

/**
 * Aktiv provayderi .env-dən (PAYMENT_DRIVER) seçir.
 *
 * Təhlükəsizlik: "fake" produksiyada QADAĞANDIR. Əks halda kimsə saxta "Uğurlu" düyməsi ilə
 * pulsuz giriş əldə edə bilərdi. Produksiyada fake seçilibsə alış cəhdi xəta verir və
 * "Al" düyməsi ümumiyyətlə göstərilmir (bax: available()).
 */
class PaymentGatewayFactory
{
    public function make(?string $driver = null): PaymentGateway
    {
        $driver ??= (string) config('payments.driver');

        if ($driver === 'fake' && app()->isProduction()) {
            throw new RuntimeException(
                'Sınaq ödəniş provayderi ("fake") produksiyada işlədilə bilməz. '
                .'.env faylında PAYMENT_DRIVER real provayderə dəyişdirilməlidir.'
            );
        }

        return match ($driver) {
            'fake' => new FakePaymentGateway,
            default => throw new RuntimeException("Ödəniş provayderi tanınmır: \"{$driver}\"."),
        };
    }

    /** Alış mümkündürmü (UI-da "Al" düyməsi göstərilsinmi) */
    public function available(): bool
    {
        try {
            $this->make();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
