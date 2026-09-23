<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sinif səviyyəsi etiketləri: 2-ci sinifdən 11-ci sinfə.
 *
 * Açar `kind` + `order` cütüdür (slug yox): ad və ya slug düzəldiləndə mövcud sətir
 * yenilənir, imtahanlarla əlaqə isə qalır. `is_active` yalnız yaradılanda təyin olunur
 * ki, adminin söndürdüyü etiket geri açılmasın.
 *
 * Sərbəst etiketlər (`kind = other`) burada yoxdur: onları admin özü yaradır.
 */
class TagSeeder extends Seeder
{
    /**
     * Sıra sayının şəkilçisi Azərbaycan dilində ahəng qanununa görə dəyişir:
     * 2-ci, 3-cü, 4-cü, 5-ci, 6-cı, 7-ci, 8-ci, 9-cu, 10-cu, 11-ci.
     * Avtomatik qurmaq olmur — siyahı açıq yazılır.
     *
     * @var array<int, string>
     */
    private const GRADES = [
        2 => '2-ci',
        3 => '3-cü',
        4 => '4-cü',
        5 => '5-ci',
        6 => '6-cı',
        7 => '7-ci',
        8 => '8-ci',
        9 => '9-cu',
        10 => '10-cu',
        11 => '11-ci',
    ];

    public function run(): void
    {
        foreach (self::GRADES as $grade => $ordinal) {
            $tag = Tag::firstOrNew(['kind' => Tag::KIND_GRADE, 'order' => $grade]);

            $tag->fill([
                // Slug ASCII olur: "6-cı" → "6-ci-sinif" (rəqəm fərqli olduğu üçün toqquşma yoxdur)
                'slug' => Str::slug($ordinal.' sinif'),
                'name' => $ordinal.' sinif',
            ]);

            if (! $tag->exists) {
                $tag->is_active = true;
            }

            $tag->save();
        }
    }
}
