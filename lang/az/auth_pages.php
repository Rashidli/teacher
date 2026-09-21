<?php

// Giriş, qeydiyyat, parolun bərpası səhifələri
return [
    'fields' => [
        'first_name' => 'Ad',
        'last_name' => 'Soyad',
        'email' => 'Email',
        'phone' => 'Mobil nömrə',
        'password' => 'Parol',
        'password_confirmation' => 'Parolun təkrarı',
        'new_password' => 'Yeni parol',
        'remember' => 'Məni xatırla',
    ],

    'password_show' => 'Parolu göstər',
    'password_hide' => 'Parolu gizlət',
    'errors_summary' => 'Formada səhv var. Qırmızı ilə qeyd olunan sahələri düzəlt.',

    'login' => [
        'title' => 'Giriş',
        'card_label' => 'Giriş',
        'heading' => 'Hesabına daxil ol',
        'lead' => 'Sınaq testlərinə və nəticələrinə davam et.',
        'forgot' => 'Parolu unutmusan?',
        'submit' => 'Daxil ol',
        'no_account' => 'Hesabın yoxdur?',
        'register_link' => 'Qeydiyyatdan keç',
    ],

    'register' => [
        'title' => 'Qeydiyyat',
        'card_label' => 'Qeydiyyat',
        'heading' => 'Pulsuz hesab yarat',
        'lead' => 'Qeydiyyatdan sonra ilk sınaq testinə dərhal başlaya bilərsən.',
        'phone_hint' => 'Operator kodu: 10, 50, 51, 55, 60, 70, 77 və ya 99',
        'phone_invalid' => 'Mobil nömrə düzgün deyil. Format: +994 XX XXX XX XX, operator kodu 10, 50, 51, 55, 60, 70, 77 və ya 99.',
        'phone_taken' => 'Bu mobil nömrə ilə artıq qeydiyyat var.',
        'password_hint' => 'Ən azı 8 simvol',
        // ":link" yerinə "terms_link" mətni ilə link qoyulur
        'terms_label' => ':link ilə razıyam',
        'terms_link' => 'Şərtlər və qaydalar',
        'terms_new_tab' => '(yeni tabda açılır)',
        'terms_required' => 'Qeydiyyat üçün şərtlər və qaydalarla razılaşmalısan.',
        'submit' => 'Qeydiyyatdan keç',
        'have_account' => 'Hesabın var?',
        'login_link' => 'Daxil ol',
    ],

    'forgot' => [
        'title' => 'Parolun bərpası',
        'card_label' => 'Parolun bərpası',
        'heading' => 'Parolu unutmusan?',
        'lead' => 'Email ünvanını yaz, parolu yeniləmək üçün link göndərəcəyik.',
        'submit' => 'Link göndər',
        'back' => 'Girişə qayıt',
    ],

    'reset' => [
        'title' => 'Yeni parol',
        'card_label' => 'Parolun bərpası',
        'heading' => 'Yeni parol təyin et',
        'lead' => 'Yeni parolu iki dəfə yaz.',
        'submit' => 'Parolu yenilə',
    ],
];
