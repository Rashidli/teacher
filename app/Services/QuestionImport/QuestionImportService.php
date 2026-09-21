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
    /** Fayldakı tip adları → bazadakı tiplər */
    public const TYPE_ALIASES = [
        'test' => Question::TYPE_MULTIPLE_CHOICE,
        'qisa' => Question::TYPE_OPEN_CODED,
        'qısa' => Question::TYPE_OPEN_CODED,
        'aciq' => Question::TYPE_OPEN_WRITTEN,
        'açıq' => Question::TYPE_OPEN_WRITTEN,
    ];

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
                    'topic_id' => $row->topicId,
                    'difficulty' => $row->difficulty,
                    'question_text' => $row->questionText,
                    'type' => $row->type,
                    'accepted_answers' => $row->type === Question::TYPE_OPEN_CODED
                        ? $row->acceptedAnswers
                        : null,
                    'explanation' => $row->explanation,
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
            $errors[] = $rawType === ''
                ? 'Tip sütunu boşdur (test / qisa / aciq).'
                : "Tip tanınmadı: \"{$rawType}\" (test / qisa / aciq olmalıdır).";
        }

        $correct = (string) ($row['duzgun'] ?? '');
        $options = [];
        $acceptedAnswers = [];

        if ($type === Question::TYPE_MULTIPLE_CHOICE) {
            [$options, $optionErrors] = $this->parseOptions($row, $correct, $exam);
            $errors = array_merge($errors, $optionErrors);
        }

        if ($type === Question::TYPE_OPEN_CODED) {
            $acceptedAnswers = collect(explode(self::ANSWER_SEPARATOR, $correct))
                ->map(fn (string $value) => trim($value))
                ->filter(fn (string $value) => $value !== '')
                ->values()
                ->all();

            if ($acceptedAnswers === []) {
                $errors[] = 'Qısa cavablı sualda "duzgun" sütunu boş ola bilməz.';
            }
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
            errors: $errors,
            topicId: $topicId,
            difficulty: $difficulty,
            topicName: $topicName !== '' ? $topicName : null,
        );
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
