<?php

namespace App\Services\Payment;

/**
 * Bankın callback sorğusundan oxunan nəticə: hansı ödəniş və uğurlu olub-olmaması.
 */
class CallbackResult
{
    public function __construct(
        public readonly string $reference,
        public readonly bool $successful,
        public readonly array $payload = [],
    ) {
    }
}
