<?php

namespace Tests\Unit;

use App\Support\Slug;
use PHPUnit\Framework\TestCase;

/**
 * Slug qaydası kateqoriya slug-ları ilə eyni olmalıdır (CategorySeeder-dəki əl ilə yazılmış
 * "9-cu-sinif-buraxilis", "dovlet-qullugu" kimi ünvanlar).
 */
class SlugTest extends TestCase
{
    /**
     * @dataProvider azerbaijaniLetters
     */
    public function test_each_azerbaijani_letter_maps_to_its_latin_pair(string $letter, string $expected): void
    {
        $this->assertSame($expected, Slug::make($letter));
    }

    public static function azerbaijaniLetters(): array
    {
        return [
            'ə' => ['ə', 'e'],
            'Ə' => ['Ə', 'e'],
            'ı' => ['ı', 'i'],
            'İ' => ['İ', 'i'],
            'ö' => ['ö', 'o'],
            'Ö' => ['Ö', 'o'],
            'ü' => ['ü', 'u'],
            'Ü' => ['Ü', 'u'],
            'ş' => ['ş', 's'],
            'Ş' => ['Ş', 's'],
            'ç' => ['ç', 'c'],
            'Ç' => ['Ç', 'c'],
            'ğ' => ['ğ', 'g'],
            'Ğ' => ['Ğ', 'g'],
        ];
    }

    /**
     * @dataProvider realTitles
     */
    public function test_real_exam_titles_become_readable_urls(string $title, string $expected): void
    {
        $this->assertSame($expected, Slug::make($title));
    }

    public static function realTitles(): array
    {
        return [
            ['Azərbaycan Tarixi — Buraxılış İmtahanı 2024', 'azerbaycan-tarixi-buraxilis-imtahani-2024'],
            ['Dövlət qulluğu üçün sınaq', 'dovlet-qullugu-ucun-sinaq'],
            ['Çətin söz: ölçü, güzgü, ağıl', 'cetin-soz-olcu-guzgu-agil'],
            ['9-cu sinif buraxılış', '9-cu-sinif-buraxilis'],
            ['I qrup — RK mövzu sınağı', 'i-qrup-rk-movzu-sinagi'],
            // Rus başlığı da oxunaqlı qalır (ru sektoru imtahanları)
            ['Пробный экзамен', 'probnyi-ekzamen'],
        ];
    }

    public function test_repeated_titles_get_a_numeric_suffix(): void
    {
        $taken = ['sinaq-imtahani', 'sinaq-imtahani-2'];

        $this->assertSame(
            'sinaq-imtahani-3',
            Slug::unique('Sınaq imtahanı', fn (string $slug) => in_array($slug, $taken, true)),
        );
    }

    /** Latın hərfi olmayan başlıq slug-sız qalmamalıdır. */
    public function test_a_title_without_letters_falls_back(): void
    {
        $this->assertSame('imtahan', Slug::unique('!!! ???', fn () => false));
    }
}
