<?php

/*
| Tək guard ("web"), tək istifadəçi cədvəli (users).
|
| Rol ayrı guard deyil: admin/teacher/student spatie rolları ilə verilir və panellər
| `auth` + rol middleware-i ilə qorunur (EnsureUserIsAdmin/Teacher/Student).
| Bir hesabın eyni anda bir neçə rolu ola bilər.
*/
return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
