<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sual bankı ilə iş: sual yaradılır, imtahanlara bağlanır və sıralanır.
 *
 * Sual imtahana yox, fənnə aiddir. İmtahanla əlaqə `exam_question` pivotundadır və sıra da
 * ordadır — eyni sual iki imtahanda fərqli yerdə dura bilər.
 */
class QuestionService
{
    private const QUESTION_IMAGE_DIR = 'questions';

    private const OPTION_IMAGE_DIR = 'question-options';

    /** Bankda yeni sual yaradır və imtahana bağlayır. */
    public function create(Exam $exam, array $data): Question
    {
        return DB::transaction(function () use ($exam, $data) {
            $question = $this->createInBank($data + ['subject_id' => $exam->subject_id]);

            $this->attach($exam, $question);

            return $question;
        });
    }

    /** Yalnız bankda sual yaradır (imtahana bağlamadan). */
    public function createInBank(array $data): Question
    {
        return DB::transaction(function () use ($data) {
            $attributes = $this->questionAttributes($data);

            if (($data['question_image'] ?? null) instanceof UploadedFile) {
                $attributes['question_image'] = $data['question_image']->store(self::QUESTION_IMAGE_DIR, 'public');
            }

            $question = Question::create($attributes);

            $this->syncOptions($question, $data);

            return $question;
        });
    }

    public function update(Question $question, array $data): Question
    {
        $this->guardAgainstScoringChanges($question, $data);

        return DB::transaction(function () use ($question, $data) {
            $attributes = $this->questionAttributes($data, $question);

            if (($data['question_image'] ?? null) instanceof UploadedFile) {
                $this->deleteFile($question->question_image);
                $attributes['question_image'] = $data['question_image']->store(self::QUESTION_IMAGE_DIR, 'public');
            } elseif (filter_var($data['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->deleteFile($question->question_image);
                $attributes['question_image'] = null;
            }

            $question->update($attributes);

            $this->syncOptions($question, $data);

            return $question->refresh();
        });
    }

    /**
     * Cəhdlərdə işlənmiş sualda BAL NƏTİCƏSİNƏ təsir edən dəyişikliklər bloklanır:
     * sual tipi, düzgün cavab, variant dəsti və qəbul olunan cavablar.
     *
     * Bunlar dəyişsə keçilmiş cəhdlərin nəticəsi mənasını itirər (bal yenidən hesablanmır,
     * amma nəticə səhifəsi yeni "düzgün cavabı" göstərər). Belə hallarda sualın kopyası
     * yaradılıb imtahanda əvəzlənməlidir — `duplicateInto()`.
     *
     * Mətn, izah, mənbə, çətinlik, mövzu və şəkil dəyişikliyi sərbəstdir.
     */
    private function guardAgainstScoringChanges(Question $question, array $data): void
    {
        if ($question->attemptUsageCount() === 0) {
            return;
        }

        $errors = [];

        if (($data['type'] ?? $question->type) !== $question->type) {
            $errors['type'] = 'Bu sual şagird cəhdlərində istifadə olunub: sual tipi dəyişdirilə bilməz.';
        }

        if ($question->type === Question::TYPE_MULTIPLE_CHOICE && isset($data['options'])) {
            $errors += $this->optionChangeErrors($question, $data['options']);
        }

        if ($question->type === Question::TYPE_OPEN_CODED && isset($data['accepted_answers'])) {
            $before = $this->normalisedAnswers((array) $question->accepted_answers);
            $after = $this->normalisedAnswers((array) $data['accepted_answers']);

            if ($before !== $after) {
                $errors['accepted_answers'] = 'Bu sual şagird cəhdlərində istifadə olunub: '
                    .'qəbul olunan cavablar dəyişdirilə bilməz.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors + [
                'question' => 'Dəyişikliyi tətbiq etmək üçün "Kopyala və imtahanda əvəzlə" '
                    .'seçimindən istifadə edin — köhnə nəticələr toxunulmaz qalacaq.',
            ]);
        }
    }

    /** @return array<string, string> */
    private function optionChangeErrors(Question $question, array $options): array
    {
        $currentLetters = $question->options->pluck('option_letter')->sort()->values()->all();
        $newLetters = collect($options)->pluck('option_letter')->sort()->values()->all();

        if ($currentLetters !== $newLetters) {
            return ['options' => 'Bu sual şagird cəhdlərində istifadə olunub: variantlar əlavə edilə '
                .'və ya silinə bilməz.'];
        }

        $currentCorrect = $question->options->firstWhere('is_correct', true)?->option_letter;
        $newCorrect = collect($options)->first(fn ($option) => filter_var(
            $option['is_correct'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        ))['option_letter'] ?? null;

        if ($currentCorrect !== $newCorrect) {
            return ['options' => 'Bu sual şagird cəhdlərində istifadə olunub: düzgün cavab '
                .'dəyişdirilə bilməz.'];
        }

        return [];
    }

    /** @param  array<int, mixed>  $answers */
    private function normalisedAnswers(array $answers): array
    {
        $values = array_map(fn ($value) => trim((string) $value), $answers);
        sort($values);

        return $values;
    }

    /**
     * Sualın kopyasını yaradır və imtahanda onunla əvəzləyir.
     * Cəhdlərdə işlənmiş sualı dəyişmək əvəzinə istifadə olunur: köhnə nəticələr toxunulmaz qalır.
     */
    public function duplicateInto(Exam $exam, Question $question): Question
    {
        return DB::transaction(function () use ($exam, $question) {
            $copy = Question::create($question->only([
                'subject_id', 'topic_id', 'question_text', 'question_image', 'type',
                'difficulty', 'accepted_answers', 'explanation', 'source', 'is_active',
            ]));

            foreach ($question->options as $option) {
                $copy->options()->create($option->only([
                    'option_letter', 'option_text', 'option_image', 'is_correct', 'order',
                ]));
            }

            // Kopya köhnə sualın yerini tutur
            $order = $exam->questions()->where('questions.id', $question->id)->first()?->pivot?->order;

            $exam->questions()->detach($question->id);
            $exam->questions()->attach($copy->id, ['order' => $order ?? $this->nextOrder($exam)]);

            return $copy;
        });
    }

    /**
     * Sualı bankdan tamamilə silir.
     * Cəhdlərdə işlənmiş sual silinmir — o, yalnız imtahandan ayrıla bilər.
     */
    public function delete(Question $question): void
    {
        if ($question->attemptUsageCount() > 0) {
            throw new RuntimeException(
                'Bu sual şagird cəhdlərində istifadə olunub və silinə bilməz. '
                .'Onu imtahandan ayıra bilərsiniz — bank qeydi və köhnə nəticələr qalır.'
            );
        }

        DB::transaction(function () use ($question) {
            $this->deleteFile($question->question_image);
            $this->deleteOptionImages($question);

            $question->exams()->detach();
            $question->delete();
        });
    }

    /** Mövcud bank sualını imtahana bağlayır. */
    public function attach(Exam $exam, Question $question): void
    {
        if ($exam->questions()->where('questions.id', $question->id)->exists()) {
            return;
        }

        $exam->questions()->attach($question->id, ['order' => $this->nextOrder($exam)]);
    }

    /** Sualı imtahandan ayırır — bankdan silmir. */
    public function detach(Exam $exam, Question $question): void
    {
        DB::transaction(function () use ($exam, $question) {
            $exam->questions()->detach($question->id);
            $this->resequence($exam);
        });
    }

    /** Sualı imtahanda bir mövqe yuxarı/aşağı sürüşdürür. */
    public function move(Exam $exam, Question $question, string $direction): void
    {
        DB::transaction(function () use ($exam, $question, $direction) {
            $current = $exam->questions()->where('questions.id', $question->id)->first();

            if (! $current) {
                return;
            }

            /*
             * reorder(): əlaqədə sabit `orderBy('exam_question.order')` var, əks halda buradakı
             * sıralama ikinci dərəcəli qalır və qonşu səhv seçilir.
             * when() istifadə olunmur: reorder() əlaqəni sorğu obyektinə çevirir və nəticə itir.
             */
            $query = $exam->questions();

            $neighbour = $direction === 'up'
                ? $query->wherePivot('order', '<', $current->pivot->order)
                    ->reorder('exam_question.order', 'desc')->first()
                : $query->wherePivot('order', '>', $current->pivot->order)
                    ->reorder('exam_question.order', 'asc')->first();

            if (! $neighbour) {
                return;
            }

            $exam->questions()->updateExistingPivot($question->id, ['order' => $neighbour->pivot->order]);
            $exam->questions()->updateExistingPivot($neighbour->id, ['order' => $current->pivot->order]);
        });
    }

    /** Sıra nömrələrini 1-dən başlayaraq boşluqsuz yenidən yazır. */
    public function resequence(Exam $exam): void
    {
        DB::transaction(function () use ($exam) {
            $exam->questions()->get()->each(
                fn (Question $question, int $index) => $exam->questions()
                    ->updateExistingPivot($question->id, ['order' => $index + 1])
            );
        });
    }

    private function nextOrder(Exam $exam): int
    {
        return (int) (DB::table('exam_question')->where('exam_id', $exam->id)->max('order') ?? 0) + 1;
    }

    /**
     * Tipə uyğun olmayan sahələr təmizlənir: variantlı sualda accepted_answers,
     * açıq sualda isə variantlar saxlanılmır.
     */
    private function questionAttributes(array $data, ?Question $question = null): array
    {
        $type = $data['type'];

        return [
            'subject_id' => $data['subject_id'] ?? $question?->subject_id,
            'topic_id' => array_key_exists('topic_id', $data) ? $data['topic_id'] : $question?->topic_id,
            'question_text' => $data['question_text'],
            'type' => $type,
            'difficulty' => $data['difficulty'] ?? $question?->difficulty ?? Question::DIFFICULTY_MEDIUM,
            'source' => $data['source'] ?? $question?->source,
            'explanation' => $data['explanation'] ?? null,
            'accepted_answers' => $type === Question::TYPE_OPEN_CODED
                ? array_values($data['accepted_answers'] ?? [])
                : null,
        ];
    }

    private function syncOptions(Question $question, array $data): void
    {
        if ($question->type !== Question::TYPE_MULTIPLE_CHOICE) {
            $this->deleteOptionImages($question);
            $question->options()->delete();

            return;
        }

        $options = array_values($data['options'] ?? []);

        /*
         * Variantlar hər dəfə yenidən yazılır (sıra və hərflər dəyişə bilər), amma şəkillər
         * hərfə görə saxlanılır: admin yalnız mətni düzəldəndə mövcud variant şəkli itməməlidir.
         */
        $existingImages = $question->options()->pluck('option_image', 'option_letter')->all();
        $keptImages = [];

        $question->options()->delete();

        foreach ($options as $index => $option) {
            $image = ($option['option_image'] ?? null) instanceof UploadedFile
                ? $option['option_image']->store(self::OPTION_IMAGE_DIR, 'public')
                : ($existingImages[$option['option_letter']] ?? null);

            if ($image) {
                $keptImages[] = $image;
            }

            $question->options()->create([
                'option_letter' => $option['option_letter'],
                'option_text' => $option['option_text'],
                'option_image' => $image,
                'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'order' => $index + 1,
            ]);
        }

        // Artıq heç bir variantın istifadə etmədiyi şəkillər diskdən silinir
        foreach ($existingImages as $path) {
            if ($path && ! in_array($path, $keptImages, true)) {
                $this->deleteFile($path);
            }
        }
    }

    private function deleteOptionImages(Question $question): void
    {
        $question->options()->pluck('option_image')
            ->each(fn (?string $path) => $this->deleteFile($path));
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
