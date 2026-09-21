<?php

namespace App\Services\Payment;

/**
 * Bankdan gələn cavab olduğu kimi saxlanılmır: kart nömrəsi, CVV, son istifadə tarixi və
 * digər həssas sahələr atılır. Maskalanmış nömrə (məs. "4169 **** **** 1234) saxlanıla bilər.
 */
class PaymentPayloadSanitizer
{
    /** Maskalanmamış kart nömrəsi: 13-19 rəqəm (boşluq/defis ola bilər) */
    private const BARE_CARD_NUMBER = '/(?:\d[ -]?){13,19}/';

    public static function clean(array $payload): array
    {
        $blocked = array_map('mb_strtolower', (array) config('payments.payload_blocklist', []));

        return self::walk($payload, $blocked);
    }

    private static function walk(array $payload, array $blocked): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(mb_strtolower($key), $blocked, true)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::walk($value, $blocked);

                continue;
            }

            $clean[$key] = is_string($value) ? self::maskCardNumbers($value) : $value;
        }

        return $clean;
    }

    /** Mətn içindəki tam kart nömrəsi son 4 rəqəmdən başqa gizlədilir. */
    private static function maskCardNumbers(string $value): string
    {
        return preg_replace_callback(self::BARE_CARD_NUMBER, function (array $matches) {
            $digits = preg_replace('/\D/', '', $matches[0]);

            if (strlen($digits) < 13) {
                return $matches[0];
            }

            return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
        }, $value) ?? $value;
    }
}
