<?php

namespace App\Services\QuestionImport;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Excel/CSV faylından toplu sual importu.
 *
 * İki addımlıdır: əvvəlcə fayl oxunur və hər sətir yoxlanır (önizləmə), yalnız admin təsdiq
 * edəndən sonra bazaya yazılır. Yazma bütöv bir tranzaksiyadadır — bir sətir xətalıdırsa
 * heç nə yazılmır, yarımçıq import qalmır.
 */
class QuestionImportService
{
    /**
     * Fayldakı tip adları → bazadakı tiplər.
     *
     * DİM-in kodlaşdırılan tapşırıqları fayl tərəfində AYRI ad kimi yazılır (secim,
     * ardicilliq, uygunluq): beləcə "tip" sütunu bir sözdür və alt növ üçün əlavə sütun
     * doldurmaq lazım gəlmir. Hamısı `open_coded` tipinə düşür, fərq alt növdədir.
     */
    public const TYPE_ALIASES = [
        'test' => Question::TYPE_MULTIPLE_CHOICE,
        'qisa' => Question::TYPE_OPEN_CODED,
        'qısa' => Question::TYPE_OPEN_CODED,
        'hesablama' => Question::TYPE_OPEN_CODED,
        'secim' => Question::TYPE_OPEN_CODED,
        'seçim' => Question::TYPE_OPEN_CODED,
        'ardicilliq' => Question::TYPE_OPEN_CODED,
        'ardıcıllıq' => Question::TYPE_OPEN_CODED,
        'uygunluq' => Question::TYPE_OPEN_CODED,
        'uyğunluq' => Question::TYPE_OPEN_CODED,
        'aciq' => Question::TYPE_OPEN_WRITTEN,
        'açıq' => Question::TYPE_OPEN_WRITTEN,
    ];

    /** Fayldakı ad → kodlaşdırılan alt növ (yazılmayıbsa hesablama) */
    public const SUBTYPE_ALIASES = [
        'qisa' => Question::CODED_NUMERIC,
        'qısa' => Question::CODED_NUMERIC,
        'hesablama' => Question::CODED_NUMERIC,
        'secim' => Question::CODED_MULTI_SELECT,
        'seçim' => Question::CODED_MULTI_SELECT,
        'ardicilliq' => Question::CODED_ORDERING,
        'ardıcıllıq' => Question::CODED_ORDERING,
        'uygunluq' => Question::CODED_MATCHING,
        'uyğunluq' => Question::CODED_MATCHING,
    ];

    /** Yazılı tapşırığın alt növü ("alt_tip" sütunu) */
    public const WRITTEN_SUBTYPE_ALIASES = [
        'serbest' => Question::WRITTEN_FREE,
        'sərbəst' => Question::WRITTEN_FREE,
        'situasiya' => Question::WRITTEN_SITUATION,
        'metn' => Question::WRITTEN_TEXT,
        'mətn' => Question::WRITTEN_TEXT,
        'menbe' => Question::WRITTEN_SOURCE,
        'mənbə' => Question::WRITTEN_SOURCE,
        'isbat' => Question::WRITTEN_PROOF,
        'isbat/sübut' => Question::WRITTEN_PROOF,
    ];

    /** Uyğunluq cütlərində sol və sağ bənd bu işarə ilə ayrılır: "Bakı=Azərbaycan" */
    private const PAIR_SEPARATOR = '=';

    public const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /** Qısa cavabdakı alternativlər bu işarə ilə ayrılır */
    private const ANSWER_SEPARATOR = '|';

    /** Fayldakı çətinlik adları → bazadakı dəyərlər */
    public const DIFFICULTY_ALIASES = [
        'sade' => Question::DIFFICULTY_EASY,
        'sadə' => Question::DIFFICULTY_EASY,
        'orta' => Question::DIFFICULTY_MEDIUM,
        'murekkeb' => Question::DIFFICULTY_HARD,
        'mürəkkəb' => Question::DIFFICULTY_HARD,
    ];

    /**
     * Faylı oxuyur və hər sətri yoxlayır. Baza dəyişmir.
     *
     * @return array<int, ImportedQuestionRow>
     */
    public function parse(string $absolutePath, Exam $exam): array
    {
        $sheets = Excel::toArray(new QuestionImportSheet, $absolutePath);
        $rows = $sheets[0] ?? [];

        $parsed = [];
        $number = 0;

        foreach ($rows as $row) {
            $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

            // Tamamilə boş sətirlər sayılmır (fayl sonundakı boşluqlar)
            if (collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            $parsed[] = $this->parseRow($row, ++$number, $exam);
        }

        return $parsed;
    }

    /**
     * Təsdiqdən sonra yazma. Bütün sətirlər etibarlı olmalıdır.
     *
     * @param  array<int, ImportedQuestionRow>  $rows
     * @return int  yazılan sual sayı
     */
    public function import(Exam $exam, array $rows): int
    {
        $invalid = collect($rows)->reject(fn (ImportedQuestionRow $row) => $row->isValid());

        if ($invalid->isNotEmpty()) {
            throw new \RuntimeException('Xətalı sətirlər var: import dayandırıldı.');
        }

        return DB::transaction(function () use ($exam, $rows) {
            // İmport imtahanın ilk bölməsinə düşür (çoxfənli imtahanda bölmə seçimi UI-dadır)
            $section = $exam->sections()->orderBy('order')->firstOrFail();
            $order = (int) (DB::table('exam_question')->where('section_id', $section->id)->max('order') ?? 0);

            foreach ($rows as $row) {
                // Sual banka yazılır (imtahanın fənninə), sonra imtahana bağlanır
                $question = Question::create([
                    'subject_id' => $section->subject_id,
                    // İmport imtahanın sektorunun dilindədir
                    'language' => $exam->sector,
                    'topic_id' => $row->topicId,
                    'difficulty' => $row->difficulty,
                    'question_text' => $row->questionText,
                    'type' => $row->type,
                    'subtype' => $row->subtype,
                    'accepted_answers' => $row->subtype === Question::CODED_NUMERIC
                        ? $row->acceptedAnswers
                        : null,
                    // Cütlərin sırası düzgün cavabdır (bax `App\Support\CodedAnswer`)
                    'pairs' => $row->subtype === Question::CODED_MATCHING ? $row->pairs : null,
                    'explanation' => $row->explanation,
                    'grading_rubric' => $row->gradingRubric,
                ]);

                $exam->questions()->attach($question->id, [
                    'section_id' => $section->id,
                    'order' => ++$order,
                ]);

                foreach ($row->options as $index => $option) {
                    $question->options()->create([
                        'option_letter' => $option['option_letter'],
                        'option_text' => $option['option_text'],
                        'is_correct' => $option['is_correct'],
                        'order' => $index + 1,
                    ]);
                }
            }

            return count($rows);
        });
    }

    private function parseRow(array $row, int $number, Exam $exam): ImportedQuestionRow
    {
        $errors = [];

        $questionText = (string) ($row['sual'] ?? '');
        if ($questionText === '') {
            $errors[] = 'Sual mətni boşdur.';
        }

        $rawType = mb_strtolower((string) ($row['tip'] ?? ''), 'UTF-8');
        $type = self::TYPE_ALIASES[$rawType] ?? null;

        if ($type === null) {
            $names = 'test / hesablama / secim / ardicilliq / uygunluq / aciq';
            $errors[] = $rawType === ''
                ? "Tip sütunu boşdur ({$names})."
                : "Tip tanınmadı: \"{$rawType}\" ({$names} olmalıdır).";
        }

        $correct = (string) ($row['duzgun'] ?? '');
        $options = [];
        $acceptedAnswers = [];
        $pairs = [];
        $subtype = $this->subtype($row, $rawType, $type);

        if ($type === Question::TYPE_MULTIPLE_CHOICE) {
            [$options, $optionErrors] = $this->parseOptions($row, $correct, $exam);
            $errors = array_merge($errors, $optionErrors);
        }

        if ($subtype === Question::CODED_NUMERIC) {
            $acceptedAnswers = collect(explode(self::ANSWER_SEPARATOR, $correct))
                ->map(fn (string $value) => trim($value))
                ->filter(fn (string $value) => $value !== '')
                ->values()
                ->all();

            if ($acceptedAnswers === []) {
                $errors[] = 'Hesablama sualında "duzgun" sütunu boş ola bilməz.';
            }
        }

        /*
         * Seçim: variantlar variant_a… sütunlarındadır, düzgünlər isə "duzgun" sütununda
         * hərflə sadalanır ("A|C"). Ardıcıllıq: variantlar DÜZGÜN sıra ilə yazılır,
         * "duzgun" sütunu isə lazım deyil.
         */
        if (in_array($subtype, [Question::CODED_MULTI_SELECT, Question::CODED_ORDERING], true)) {
            [$options, $listErrors] = $this->parseList($row, $correct, $subtype);
            $errors = array_merge($errors, $listErrors);
        }

        if ($subtype === Question::CODED_MATCHING) {
            [$pairs, $pairErrors] = $this->parsePairs($correct);
            $errors = array_merge($errors, $pairErrors);
        }

        // Mövzu: fayldakı ad imtahanın fənnindəki mövzularla tutuşdurulur
        $topicName = (string) ($row['movzu'] ?? '');
        $topicId = null;

        if ($topicName !== '') {
            $topicId = Topic::where('subject_id', $exam->subject_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($topicName, 'UTF-8')])
                ->value('id');

            if ($topicId === null) {
                $errors[] = "Mövzu tapılmadı: \"{$topicName}\" (bu fənnin mövzuları arasında yoxdur).";
            }
        }

        $rawDifficulty = mb_strtolower((string) ($row['cetinlik'] ?? ''), 'UTF-8');
        $difficulty = $rawDifficulty === ''
            ? Question::DIFFICULTY_MEDIUM
            : (self::DIFFICULTY_ALIASES[$rawDifficulty] ?? null);

        if ($difficulty === null) {
            $errors[] = "Çətinlik tanınmadı: \"{$rawDifficulty}\" (sade / orta / murekkeb).";
            $difficulty = Question::DIFFICULTY_MEDIUM;
        }

        return new ImportedQuestionRow(
            number: $number,
            questionText: $questionText,
            type: $type,
            options: $options,
            acceptedAnswers: $acceptedAnswers,
            explanation: ($row['izah'] ?? '') !== '' ? (string) $row['izah'] : null,
            // Açıq yazılı sualın qiymətləndirmə meyarı (avtomatik yoxlama üçün)
            gradingRubric: ($row['meyar'] ?? '') !== '' ? (string) $row['meyar'] : null,
            errors: $errors,
            topicId: $topicId,
            difficulty: $difficulty,
            topicName: $topicName !== '' ? $topicName : null,
            subtype: $subtype,
            pairs: $pairs,
        );
    }

    /**
     * Alt növ: kodlaşdırılanda "tip" sütununun özündən (secim, ardicilliq, uygunluq),
     * yazılıda isə ayrıca "alt_tip" sütunundan gəlir. Yazılmayıbsa kodlaşdırılan sual
     * HESABLAMA sayılır — köhnə fayllar olduğu kimi işləyir.
     */
    private function subtype(array $row, string $rawType, ?string $type): ?string
    {
        if ($type === Question::TYPE_OPEN_CODED) {
            return self::SUBTYPE_ALIASES[$rawType] ?? Question::CODED_NUMERIC;
        }

        if ($type !== Question::TYPE_OPEN_WRITTEN) {
            return null;
        }

        $raw = mb_strtolower((string) ($row['alt_tip'] ?? ''), 'UTF-8');

        return self::WRITTEN_SUBTYPE_ALIASES[$raw] ?? Question::WRITTEN_FREE;
    }

    /**
     * Seçim və ardıcıllıq bəndləri: variant_a… sütunlarından oxunur.
     *
     * Ardıcıllıqda sıra DÜZGÜN cavabdır (`order`), ona görə "duzgun" sütunu boş qala bilər.
     * Seçimdə isə düzgün hərflər "A|C" kimi yazılır.
     *
     * @return array{0: array<int, array{option_letter: string, option_text: string, is_correct: bool}>, 1: array<int, string>}
     */
    private function parseList(array $row, string $correct, string $subtype): array
    {
        $errors = [];
        $options = [];

        foreach (self::LETTERS as $letter) {
            $text = (string) ($row['variant_'.mb_strtolower($letter)] ?? '');

            if ($text === '') {
                continue;
            }

            $options[] = ['option_letter' => $letter, 'option_text' => $text, 'is_correct' => false];
        }

        if (count($options) < 2) {
            $errors[] = 'Ən azı iki bənd lazımdır (variant_a, variant_b …).';
        }

        if ($subtype === Question::CODED_ORDERING) {
            return [$options, $errors];
        }

        $letters = collect(explode(self::ANSWER_SEPARATOR, $correct))
            ->map(fn (string $value) => mb_strtoupper(trim($value), 'UTF-8'))
            ->filter(fn (string $value) => $value !== '')
            ->values();

        $known = collect($options)->pluck('option_letter');
        $unknown = $letters->reject(fn (string $letter) => $known->contains($letter));

        if ($unknown->isNotEmpty()) {
            $errors[] = 'Düzgün cavabda tanınmayan hərf var: '.$unknown->implode(', ').'.';
        }

        if ($letters->count() < 2) {
            $errors[] = 'Seçim tapşırığında ən azı iki düzgün variant göstərilməlidir ("A|C").';
        }

        foreach ($options as $index => $option) {
            $options[$index]['is_correct'] = $letters->contains($option['option_letter']);
        }

        return [$options, $errors];
    }

    /**
     * Uyğunluq cütləri: "duzgun" sütununda "sol=sağ" cütləri ayrıcı ilə sadalanır
     * ("Bakı=Azərbaycan|Ankara=Türkiyə"). Cütlərin SIRASI düzgün cavabdır.
     *
     * @return array{0: array<int, array{left: string, right: string}>, 1: array<int, string>}
     */
    private function parsePairs(string $correct): array
    {
        $pairs = [];
        $errors = [];

        foreach (explode(self::ANSWER_SEPARATOR, $correct) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            $parts = array_map('trim', explode(self::PAIR_SEPARATOR, $chunk, 2));

            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                $errors[] = "Uyğunluq cütü səhvdir: \"{$chunk}\" (düzgün forma: sol=sağ).";

                continue;
            }

            $pairs[] = ['left' => $parts[0], 'right' => $parts[1]];
        }

        if (count($pairs) < 2) {
            $errors[] = 'Uyğunluq tapşırığında ən azı iki cüt olmalıdır ("Bakı=Azərbaycan|Ankara=Türkiyə").';
        }

        return [$pairs, $errors];
    }

    /**
     * @return array{0: array<int, array{option_letter: string, option_text: string, is_correct: bool}>, 1: array<int, string>}
     */
    private function parseOptions(array $row, string $correct, Exam $exam): array
    {
        $count = $exam->options_per_question;
        $letters = array_slice(self::LETTERS, 0, $count);
        $errors = [];
        $options = [];

        foreach ($letters as $letter) {
            $text = (string) ($row['variant_'.mb_strtolower($letter)] ?? '');

            if ($text === '') {
                $errors[] = "Variant {$letter} boşdur (bu imtahanda {$count} variant tələb olunur).";
            }

            $options[] = [
                'option_letter' => $letter,
                'option_text' => $text,
                'is_correct' => false,
            ];
        }

        // İmtahanda 4 variant varsa, faylda E doldurulubsa xəbərdarlıq verilir
        foreach (array_slice(self::LETTERS, $count) as $extraLetter) {
            if ((string) ($row['variant_'.mb_strtolower($extraLetter)] ?? '') !== '') {
                $errors[] = "Variant {$extraLetter} doludur, amma bu imtahanda yalnız {$count} variant olmalıdır.";
            }
        }

        $correctLetter = mb_strtoupper(trim($correct), 'UTF-8');

        if ($correctLetter === '') {
            $errors[] = 'Düzgün cavab göstərilməyib ("duzgun" sütunu).';
        } elseif (! in_array($correctLetter, $letters, true)) {
            $errors[] = "Düzgün cavab \"{$correctLetter}\" variantlar arasında deyil (".implode(', ', $letters).').';
        } else {
            foreach ($options as $index => $option) {
                $options[$index]['is_correct'] = $option['option_letter'] === $correctLetter;
            }
        }

        return [$options, $errors];
    }
}
