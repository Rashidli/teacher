<?php

namespace App\Services\Payment;

use RuntimeException;

/**
 * Aktiv provayderi .env-dən (PAYMENT_DRIVER) seçir.
 *
 * TƏK AÇAR: `PAYMENT_DRIVER=fake` test rejimidir — ödəniş dərhal "ödənildi" olur, real pul
 * hərəkət etmir. Real bank inteqrasiyası gələndə dəyişən yeni driverə çevrilir və test
 * rejimi bir addımla sönür.
 *
 * DİQQƏT: fake driver produksiyada da işləyir (sayt müvəqqəti subdomendədir, real istifadəçi
 * və data yoxdur). ÖZ DOMENİMİZƏ KEÇMƏZDƏN ƏVVƏL `PAYMENT_DRIVER` real provayderə
 * dəyişdirilməlidir — əks halda imtahanlar faktiki olaraq pulsuz olar. Bax: ROADMAP (P2.5)
 * və TESTING.md.
 */
class PaymentGatewayFactory
{
    public function make(?string $driver = null): PaymentGateway
    {
        $driver ??= (string) config('payments.driver');

        return match ($driver) {
            'fake' => new FakePaymentGateway,
            default => throw new RuntimeException("Ödəniş provayderi tanınmır: \"{$driver}\"."),
        };
    }

    /** Ödəniş test rejimindədir (real pul hərəkət etmir) — interfeysdə xəbərdarlıq üçün */
    public function testMode(): bool
    {
        return (string) config('payments.driver') === 'fake';
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
