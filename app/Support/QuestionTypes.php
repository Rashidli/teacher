<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Question;

/**
 * Hansı kateqoriyada hansı sual tipləri işlənə bilər (`config/questions.php`).
 *
 * Sürücülük imtahanında açıq sual yoxdur, MİQ və sertifikasiyada yalnız qapalı test var,
 * abituriyent II mərhələsində isə hər üç tip işlənir. Qayda bir yerdə saxlanılır və üç
 * yerdə tətbiq olunur: admin sual forması, bankdan generasiya və nümunə məzmun seeder-i.
 *
 * Uyğunluq ƏN UZUN PREFİKSƏ görədir, ona görə alt düyün valideyndən daha dəqiq qayda
 * təyin edə bilər (`dovlet-qullugu/tam-sinaq/bb-ac` yalnız qapalı).
 */
class QuestionTypes
{
    /** Kateqoriyası olmayan imtahan (köhnə qeydlər) üçün defolt */
    public const FALLBACK_KEY = '*';

    /**
     * Kateqoriya yolu üzrə icazəli tiplər.
     *
     * @return array<int, string>
     */
    public static function forPath(?string $path): array
    {
        $map = (array) config('questions.allowed_types', []);
        $default = $map[self::FALLBACK_KEY] ?? Question::TYPES;

        if (blank($path)) {
            return array_values($default);
        }

        $segments = explode('/', trim($path, '/'));

        // Ən dəqiq (ən uzun) uyğunluq axtarılır: a/b/c → a/b → a
        for ($i = count($segments); $i > 0; $i--) {
            $prefix = implode('/', array_slice($segments, 0, $i));

            if (isset($map[$prefix])) {
                return array_values($map[$prefix]);
            }
        }

        return array_values($default);
    }

    /** @return array<int, string> */
    public static function forCategory(?Category $category): array
    {
        return self::forPath($category?->path);
    }

    /**
     * İmtahanın qəbul etdiyi tiplər. Kateqoriyası olmayan imtahanda defolt işləyir.
     *
     * @return array<int, string>
     */
    public static function forExam(Exam $exam): array
    {
        return self::forPath($exam->category?->path);
    }

    public static function allows(?Category $category, string $type): bool
    {
        return in_array($type, self::forCategory($category), true);
    }

    /** Yalnız qapalı sual qəbul edən kateqoriya (sürücülük, MİQ, sertifikasiya …) */
    public static function closedOnly(?Category $category): bool
    {
        return self::forCategory($category) === [Question::TYPE_MULTIPLE_CHOICE];
    }
}
