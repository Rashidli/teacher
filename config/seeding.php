<?php

return [

    /*
    | AdminUserSeeder üçün ilkin admin hesabı. Dəyərlər yalnız .env-də saxlanılır —
    | repoda parol və ya real email olmamalıdır.
    |
    | Qeyd: seeder env()-i birbaşa oxumur. `php artisan config:cache` işlədilibsə
    | Laravel .env faylını ümumiyyətlə yükləmir və env() null qaytarır; config() isə işləyir.
    */
    'admin' => [
        'email' => env('ADMIN_SEED_EMAIL'),
        'password' => env('ADMIN_SEED_PASSWORD'),
    ],

];
