<?php

return [

    /*
    | DİM bal düsturu:
    |
    |   NBq = max(0, Dq − Yq × penalty)                      // qapalı (test) suallar
    |   NBa = Dkod + w × Σ(yazılı cavabların şkala qiymətləri)
    |   NB  = (NBq + NBa) × 100 / (Nq + Nkod + w × Nyazılı)  // 0.1-ə yuvarlaqlaşdırılır
    |   Fənn balı = NB × max_score / 100
    |
    | Nq/Nkod/Nyazılı — imtahandakı həmin növ sualların sayı.
    */

    /*
    | Yanlış cavabın cəriməsi (Yq × penalty), imtahanın mərhələsinə görə (groups.stage).
    */
    'penalty_per_wrong' => [
        'second_stage' => 0.25,  // II mərhələ: I–IV qrup
        'first_stage' => 0.0,    // I mərhələ
        'final' => 0.0,          // buraxılış
        'aptitude' => 0.0,       // V qrup (qabiliyyət)
    ],

    /*
    | DİM bal qrupu OLMAYAN imtahanda (sürücülük, MİQ, sertifikasiya, magistratura,
    | dövlət qulluğu) cərimə əmsalı. Bu imtahanlarda `exams.group_id` NULL-dur, yəni
    | mərhələ anlayışı yoxdur — yanlış cavab bal aparmır.
    |
    | KEÇİCİ: hər kateqoriyanın öz qaydası ROADMAP P3-dəki ayrıca ScoringStrategy-lərlə
    | gələcək; hazırda hamısı DİM düsturu ilə hesablanır.
    */
    'penalty_without_group' => 0.0,

    /*
    | Yazılı (open_written) sual həm məxrəcdə, həm də balda bu çəki ilə iştirak edir.
    */
    'open_written_weight' => 2,

    /*
    | Yazılı cavabın qiymətləndirmə şkalası: admin bu qiymətlərdən birini seçir.
    */
    'open_written_scale' => [0, 1 / 3, 1 / 2, 2 / 3, 1],

    /*
    | Nisbi bal (NB) bu addıma yuvarlaqlaşdırılır.
    */
    'relative_score_step' => 0.1,

    /*
    | Fənn üçün qrupda bal təyin olunmayıbsa istifadə olunur.
    */
    'default_max_score' => 100,

];
