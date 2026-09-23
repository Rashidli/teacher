<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use App\Support\Sector;
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

    /** Bankda yeni sual yaradır və imtahanın bölməsinə bağlayır. */
    public function create(Exam $exam, array $data): Question
    {
        return DB::transaction(function () use ($exam, $data) {
            $section = $this->resolveSection($exam, $data['section_id'] ?? null);

            // Yeni sual həmişə imtahanın sektorunun dilində yaranır
            $question = $this->createInBank($data + [
                'subject_id' => $section->subject_id,
                'language' => $exam->sector,
            ]);

            $this->attach($exam, $question, $section);

            return $question;
        });
    }

    /**
     * Sual hansı bölməyə düşür: göstərilibsə o, yoxsa imtahanın ilk bölməsi.
     * Hər imtahanın ən azı bir bölməsi var (migration bunu təmin edir).
     */
    private function resolveSection(Exam $exam, ?int $sectionId): ExamSection
    {
        if ($sectionId) {
            $section = $exam->sections()->whereKey($sectionId)->first();

            if ($section) {
                return $section;
            }
        }

        return $exam->sections()->orderBy('order')->firstOrFail();
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
                $attributes['question_image_alt'] = null;
            }

            $question->update($attributes);

            $this->syncOptions($question, $data);

            return $question->refresh();
        });
    }

    /**
     * Cəhdlərdə işlənmiş sualda BAL NƏTİCƏSİNƏ təsir edən dəyişikliklər bloklanır:
     * sual tipi, alt növ, düzgün cavab, variant dəsti, variantların SIRASI, uyğunluq
     * cütləri və qəbul olunan cavablar.
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

        $hasOptions = $question->type === Question::TYPE_MULTIPLE_CHOICE
            || in_array($question->codedSubtype(), [Question::CODED_MULTI_SELECT, Question::CODED_ORDERING], true);

        if ($hasOptions && isset($data['options'])) {
            $errors += $this->optionChangeErrors($question, $data['options']);
        }

        if ($question->type === Question::TYPE_OPEN_CODED
            && ($data['subtype'] ?? $question->subtype) !== $question->subtype) {
            $errors['subtype'] = 'Bu sual şagird cəhdlərində istifadə olunub: alt növ dəyişdirilə bilməz.';
        }

        // Uyğunluq cütlərinin SIRASI düzgün cavabdır: dəyişsə köhnə nəticə mənasını itirər
        if ($question->codedSubtype() === Question::CODED_MATCHING && isset($data['pairs'])) {
            if ($this->pairs((array) $question->pairs) !== $this->pairs((array) $data['pairs'])) {
                $errors['pairs'] = 'Bu sual şagird cəhdlərində istifadə olunub: uyğunluq cütləri '
                    .'dəyişdirilə bilməz.';
            }
        }

        if ($question->codedSubtype() === Question::CODED_NUMERIC && isset($data['accepted_answers'])) {
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

    /**
     * Variant dəsti, SIRASI və düzgün cavab qorunur.
     *
     * SIRA niyə: düzgün cavab artıq variantların sırasından hesablanır (`CodedAnswer`),
     * ona görə variantların yerini dəyişmək köhnə cəhdlərin nəticəsini SƏSSİZCƏ dəyişə
     * bilər — bal yenidən hesablanmır, amma nəticə səhifəsi başqa "düzgün cavab" göstərər.
     * Formada variantlar hər dəfə A, B, C … kimi yenidən hərflənir, ona görə tutuşdurma
     * hərflə yox, MƏTNLƏ aparılır.
     *
     * @return array<string, string>
     */
    private function optionChangeErrors(Question $question, array $options): array
    {
        $currentLetters = $question->options->pluck('option_letter')->sort()->values()->all();
        $newLetters = collect($options)->pluck('option_letter')->sort()->values()->all();

        if ($currentLetters !== $newLetters) {
            return ['options' => 'Bu sual şagird cəhdlərində istifadə olunub: variantlar əlavə edilə '
                .'və ya silinə bilməz.'];
        }

        $currentTexts = $question->options->sortBy('order')
            ->pluck('option_text')->map(fn ($text) => trim((string) $text))->values()->all();
        $newTexts = collect($options)
            ->pluck('option_text')->map(fn ($text) => trim((string) $text))->values()->all();

        /*
         * Ardıcıllıq tapşırığında SIRA düzgün cavabın özüdür: burada nə yerdəyişmə, nə də
         * mətn düzəlişi mümkündür — hər ikisi "hansı bənd birincidir" sualının cavabını
         * dəyişir.
         */
        if ($question->codedSubtype() === Question::CODED_ORDERING) {
            return $currentTexts === $newTexts ? [] : ['options' => 'Bu sual şagird cəhdlərində '
                .'istifadə olunub: düzgün ardıcıllıq dəyişdirilə bilməz.'];
        }

        /*
         * Digər tiplərdə mətn düzəlişi sərbəstdir (yazı səhvi), amma YERDƏYİŞMƏ deyil:
         * mətnlər eyni qalıb yerləri dəyişibsə, bu, sıra dəyişikliyidir.
         */
        $sortedCurrent = $currentTexts;
        $sortedNew = $newTexts;
        sort($sortedCurrent);
        sort($sortedNew);

        if ($sortedCurrent === $sortedNew && $currentTexts !== $newTexts) {
            return ['options' => 'Bu sual şagird cəhdlərində istifadə olunub: variantların sırası '
                .'dəyişdirilə bilməz.'];
        }

        $currentCorrect = $question->options->where('is_correct', true)
            ->pluck('option_letter')->sort()->values()->all();
        $newCorrect = collect($options)
            ->filter(fn ($option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->pluck('option_letter')->sort()->values()->all();

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
                'subject_id', 'topic_id', 'passage_id', 'question_text', 'question_image', 'question_image_alt',
                'type', 'subtype', 'language',
                'translation_group_id', 'difficulty', 'accepted_answers', 'pairs', 'explanation', 'grading_rubric',
                'source', 'is_active',
            ]));

            foreach ($question->options as $option) {
                $copy->options()->create($option->only([
                    'option_letter', 'option_text', 'option_image', 'is_correct', 'order',
                ]));
            }

            // Kopya köhnə sualın yerini (bölmə və sıra) tutur
            $pivot = $exam->questions()->where('questions.id', $question->id)->first()?->pivot;
            $section = $this->resolveSection($exam, $pivot?->section_id);

            $exam->questions()->detach($question->id);
            $exam->questions()->attach($copy->id, [
                'section_id' => $section->id,
                'order' => $pivot?->order ?? $this->nextOrder($section),
            ]);

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

    /** Mövcud bank sualını imtahanın bölməsinə bağlayır. */
    public function attach(Exam $exam, Question $question, ?ExamSection $section = null): void
    {
        $this->guardLanguageMatchesSector($exam, $question);

        if ($exam->questions()->where('questions.id', $question->id)->exists()) {
            return;
        }

        $section ??= $this->resolveSection($exam, null);

        $exam->questions()->attach($question->id, [
            'section_id' => $section->id,
            'order' => $this->nextOrder($section),
        ]);
    }

    /**
     * Sualı eyni hovuzdan başqa TƏSADÜFİ sualla əvəz edir (generasiyadan sonra admin
     * bəyənmədiyi sualı dəyişə bilsin). İmtahanda artıq olan suallar seçilmir.
     */
    public function replaceWithRandom(Exam $exam, Question $question): ?Question
    {
        $pivot = $exam->questions()->where('questions.id', $question->id)->first()?->pivot;

        if (! $pivot) {
            return null;
        }

        $used = $exam->questions()->pluck('questions.id');

        $replacement = Question::query()
            ->where('subject_id', $question->subject_id)
            // Əvəzləyən sual da imtahanın sektorunun dilində olmalıdır
            ->where('language', $exam->sector)
            ->where('is_active', true)
            ->whereNotIn('id', $used)
            // Rüb sınağıdırsa eyni rübün mövzularından seçilir
            ->when($exam->quarter !== null, fn ($query) => $query->whereHas(
                'topic',
                fn ($topic) => $exam->is_cumulative
                    ? $topic->where('quarter', '<=', $exam->quarter)
                    : $topic->where('quarter', $exam->quarter)
            ))
            ->inRandomOrder()
            ->first();

        if (! $replacement) {
            return null;
        }

        DB::transaction(function () use ($exam, $question, $replacement, $pivot) {
            $exam->questions()->detach($question->id);
            $exam->questions()->attach($replacement->id, [
                'section_id' => $pivot->section_id,
                'order' => $pivot->order,
            ]);
        });

        return $replacement;
    }

    /**
     * İmtahanın sektoru ilə sualın dili uyğun gəlməlidir: rus sektoru imtahanına Azərbaycan
     * dilində sual bağlanmamalıdır (şagird başa düşməyəcəyi sualı görməsin).
     */
    private function guardLanguageMatchesSector(Exam $exam, Question $question): void
    {
        if ($question->language === $exam->sector) {
            return;
        }

        $names = [Sector::AZ => 'Azərbaycan', Sector::RU => 'rus'];

        throw ValidationException::withMessages([
            'question_id' => sprintf(
                'Bu imtahan %s sektorundadır, sual isə %s dilindədir. Sektorlar uyğun gəlmir.',
                $names[$exam->sector] ?? $exam->sector,
                $names[$question->language] ?? $question->language,
            ),
        ]);
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

            // Sürüşdürmə yalnız öz bölməsi daxilində olur
            $sectionId = $current->pivot->section_id;

            /*
             * reorder(): əlaqədə sabit `orderBy('exam_question.order')` var, əks halda buradakı
             * sıralama ikinci dərəcəli qalır və qonşu səhv seçilir.
             * when() istifadə olunmur: reorder() əlaqəni sorğu obyektinə çevirir və nəticə itir.
             */
            $query = $exam->questions();

            $query->wherePivot('section_id', $sectionId);

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

    /** Hər bölmədə sıra nömrələrini 1-dən başlayaraq boşluqsuz yenidən yazır. */
    public function resequence(Exam $exam): void
    {
        DB::transaction(function () use ($exam) {
            foreach ($exam->sections as $section) {
                $section->questions()->get()->each(
                    fn (Question $question, int $index) => DB::table('exam_question')
                        ->where('section_id', $section->id)
                        ->where('question_id', $question->id)
                        ->update(['order' => $index + 1])
                );
            }
        });
    }

    /** Sıra bölmə daxilindədir: hər bölmənin sualları 1-dən nömrələnir. */
    private function nextOrder(ExamSection $section): int
    {
        return (int) (DB::table('exam_question')->where('section_id', $section->id)->max('order') ?? 0) + 1;
    }

    /**
     * Tipə uyğun olmayan sahələr təmizlənir: variantlı sualda accepted_answers,
     * açıq sualda isə variantlar saxlanılmır.
     */
    private function questionAttributes(array $data, ?Question $question = null): array
    {
        $type = $data['type'];
        $subtype = $this->subtype($type, $data['subtype'] ?? null);

        return [
            'subject_id' => $data['subject_id'] ?? $question?->subject_id,
            'language' => $data['language'] ?? $question?->language ?? Sector::AZ,
            'topic_id' => array_key_exists('topic_id', $data) ? $data['topic_id'] : $question?->topic_id,
            'question_text' => $data['question_text'],
            // Şəkilli sualın ekran oxuyucusu üçün təsviri (şəkil silinəndə də boşalır)
            'question_image_alt' => $data['question_image_alt'] ?? $question?->question_image_alt,
            'type' => $type,
            'subtype' => $subtype,
            /*
             * Mətn/mənbə yalnız həmin yazılı alt növlərdə saxlanılır: alt növ dəyişəndə
             * köhnə mətn suala "yapışıb" qalmamalıdır.
             */
            'passage_id' => in_array($subtype, Question::PASSAGE_SUBTYPES, true)
                ? ($data['passage_id'] ?? $question?->passage_id)
                : null,
            'difficulty' => $data['difficulty'] ?? $question?->difficulty ?? Question::DIFFICULTY_MEDIUM,
            'source' => $data['source'] ?? $question?->source,
            'explanation' => $data['explanation'] ?? null,
            // Meyar yalnız açıq yazılı sualda mənalıdır
            'grading_rubric' => $type === Question::TYPE_OPEN_WRITTEN
                ? ($data['grading_rubric'] ?? $question?->grading_rubric)
                : null,
            /*
             * Etalon cavab yalnız HESABLAMA alt növündə saxlanılır: seçim, ardıcıllıq və
             * uyğunluqda düzgün cavab variantlardan/cütlərdən hesablanır (`CodedAnswer`).
             */
            'accepted_answers' => $subtype === Question::CODED_NUMERIC
                ? array_values($data['accepted_answers'] ?? [])
                : null,
            // Cütlər yalnız uyğunluq tapşırığında doludur; sıra DÜZGÜN cavabı bildirir
            'pairs' => $subtype === Question::CODED_MATCHING
                ? $this->pairs($data['pairs'] ?? [])
                : null,
        ];
    }

    /**
     * Alt növ tipə uyğunlaşdırılır: kodlaşdırılan sualda boş dəyər HESABLAMA sayılır
     * (köhnə suallar belədir), uyğun gəlməyən dəyər isə atılır.
     */
    private function subtype(string $type, ?string $subtype): ?string
    {
        if (in_array($subtype, Question::subtypesFor($type), true)) {
            return $subtype;
        }

        return $type === Question::TYPE_OPEN_CODED ? Question::CODED_NUMERIC : null;
    }

    /**
     * Uyğunluq cütləri: boş sətirlər atılır, sıra qorunur.
     *
     * @param  array<int, mixed>  $pairs
     * @return array<int, array{left: string, right: string}>
     */
    private function pairs(array $pairs): array
    {
        return array_values(array_filter(array_map(fn ($pair) => [
            'left' => trim((string) ($pair['left'] ?? '')),
            'right' => trim((string) ($pair['right'] ?? '')),
        ], $pairs), fn (array $pair) => $pair['left'] !== '' && $pair['right'] !== ''));
    }

    /**
     * Variantlar test sualından başqa SEÇİM və ARDICILLIQ tapşırıqlarında da saxlanılır:
     * orada variantlar cavabın özüdür (seçiləcək bəndlər və düzüləcək ardıcıllıq).
     * Ardıcıllıqda `order` DÜZGÜN sıranı bildirir — şagird tərəfdə siyahı qarışdırılır.
     */
    private function syncOptions(Question $question, array $data): void
    {
        $keepsOptions = $question->type === Question::TYPE_MULTIPLE_CHOICE
            || in_array($question->subtype, [Question::CODED_MULTI_SELECT, Question::CODED_ORDERING], true);

        if (! $keepsOptions) {
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
