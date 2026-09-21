<?php

return [

    /*
    | Aktiv ödəniş provayderi. Bank qoşulanda yalnız yeni driver yazılır, qalan kod dəyişmir.
    | "fake" yalnız inkişaf/sınaq üçündür və produksiyada işləmir (PaymentGatewayFactory).
    */
    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'currency' => env('PAYMENT_CURRENCY', 'AZN'),

    /*
    | Alınan girişin müddəti (gün). null = müddətsiz.
    | Müddət təyin olunubsa, vaxtı keçmiş girişi yenidən alanda mövcud sətir uzadılır.
    */
    'access_valid_days' => env('PAYMENT_ACCESS_VALID_DAYS') ? (int) env('PAYMENT_ACCESS_VALID_DAYS') : null,

    /*
    | payload-da saxlanılmayan sahələr: kart nömrəsi, CVV, son istifadə tarixi və s.
    | Maskalanmış nömrədən (məs. 4169 **** **** 1234) başqa heç nə saxlanılmır.
    */
    'payload_blocklist' => [
        'pan', 'card_number', 'cardnumber', 'card_no', 'cardno', 'number',
        'cvv', 'cvv2', 'cvc', 'cvc2', 'csc', 'pin',
        'expiry', 'expiry_date', 'exp', 'exp_month', 'exp_year', 'expiration',
        'track', 'track1', 'track2', 'cardholder_name', 'card_holder',
        'password', 'secret', 'api_key',
    ],

];
