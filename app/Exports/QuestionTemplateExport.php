<?php

namespace App\Exports;

use App\Models\Exam;
use App\Services\QuestionImport\QuestionImportService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Toplu import şablonu: "Suallar" vərəqi (başlıqlar + nümunə sətirlər) və
 * "İzah" vərəqi (sütunların mənası, qaydalar).
 *
 * Şablon imtahana görə qurulur: variant sütunlarının sayı exam.options_per_question-dır.
 */
class QuestionTemplateExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly Exam $exam)
    {
    }

    public function sheets(): array
    {
        return [
            new QuestionTemplateSheet($this->exam),
            new QuestionTemplateHelpSheet($this->exam),
        ];
    }
}

class QuestionTemplateSheet implements Export, FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    public function __construct(private readonly Exam $exam)
    {
    }

    public function title(): string
    {
        return 'Suallar';
    }

    public function headings(): array
    {
        return array_merge(
            ['sual', 'tip'],
            array_map(
                fn (string $letter) => 'variant_'.mb_strtolower($letter),
                array_slice(QuestionImportService::LETTERS, 0, $this->exam->options_per_question)
            ),
            ['duzgun', 'movzu', 'cetinlik', 'izah', 'meyar'],
        );
    }

    public function array(): array
    {
        $count = $this->exam->options_per_question;

        $testRow = array_merge(
            ['$2 + 2 = ?$ ifadəsinin qiyməti neçədir?', 'test'],
            array_map(fn (int $index) => (string) ($index + 2), range(0, $count - 1)),
            ['C', '', 'sade', 'Sadə toplama'],
        );

        $shortRow = array_merge(
            ['$\frac{1}{2}$ kəsrini onluq şəkildə yazın', 'qisa'],
            array_fill(0, $count, ''),
            ['0,5', '', 'orta', 'Rəqəm cavab: 0.5 və 1/2 də qəbul olunur'],
        );

        $writtenRow = array_merge(
            ['Tənliyin həllini addım-addım izah edin', 'aciq'],
            array_fill(0, $count, ''),
            ['', '', 'murekkeb', 'Əl ilə qiymətləndirilir'],
        );

        return [$testRow, $shortRow, $writtenRow];
    }
}

class QuestionTemplateHelpSheet implements Export, FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    public function __construct(private readonly Exam $exam)
    {
    }

    public function title(): string
    {
        return 'İzah';
    }

    public function headings(): array
    {
        return ['Sütun', 'Mənası'];
    }

    public function array(): array
    {
        $count = $this->exam->options_per_question;
        $letters = implode(', ', array_slice(QuestionImportService::LETTERS, 0, $count));

        return [
            ['sual', 'Sual mətni. Formula üçün $...$ istifadə edin, məs: $x^2 + 3x = 0$'],
            ['tip', 'test — variantlı sual | qisa — qısa/rəqəm cavab | aciq — həll yazılır, əl ilə yoxlanır'],
            ['variant_a ...', "Yalnız \"test\" sətirlərində doldurulur. Bu imtahanda {$count} variant tələb olunur: {$letters}"],
            ['duzgun', "test üçün düzgün variantın hərfi ({$letters}). qisa üçün düzgün cavab; alternativlər | işarəsi ilə ayrılır, məs: 0,5|yarım"],
            ['movzu', 'İstəyə bağlı. Fənnin mövzularından birinin ADI (admin paneldəki "Mövzular" siyahısı). Boş buraxıla bilər.'],
            ['cetinlik', 'sade / orta / murekkeb. Boş buraxılsa "orta" sayılır.'],
            ['izah', 'İstəyə bağlı. Nəticə səhifəsində şagirdə göstərilir.'],
            ['meyar', 'Yalnız "aciq" sual üçün: düzgün cavab və qiymətləndirmə tələbləri. '
                .'Avtomatik yoxlama məhz bu mətnə görə işləyir; boş qalsa cavab əl ilə yoxlanır.'],
            ['', ''],
            ['Qeyd', 'Rəqəm cavabları ədəd kimi müqayisə olunur: 0,5 yazsanız 0.5, .5 və 1/2 də qəbul olunur.'],
            ['Qeyd', 'Fayl əvvəlcə önizləmədə yoxlanır. Bir sətirdə xəta varsa heç bir sual yazılmır.'],
            ['Qeyd', 'Şəkilləri fayl ilə yükləmək mümkün deyil — sualı əlavə etdikdən sonra redaktə səhifəsindən qoşun.'],
        ];
    }
}
