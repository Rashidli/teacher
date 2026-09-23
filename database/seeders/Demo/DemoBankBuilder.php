<?php

namespace Database\Seeders\Demo;

use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo mövzularını və sual bankını qurur.
 *
 * İdempotentdir: mövzu `subject_id + slug` (unikal indeks), sual isə `source` açarı
 * (`DEMO:fənn:dil:rüb:nömrə`) ilə tapılır. İkinci işə salmada yeni sətir yaranmır, yalnız
 * mətn dəyişibsə yenilənir — variantlar da yalnız fərq olanda yenidən yazılır.
 */
class DemoBankBuilder
{
    /** Hər mövzu (rüb) üçün sual sayı. Tək fənli rüb sınağı 8 sual götürür. */
    public const QUESTIONS_PER_TOPIC = 8;

    /** @var array<string, array<int, Topic>> fənn slug → rüb sırası ilə mövzular */
    private array $topics = [];

    /** @var array<string, Collection<int, Question>> "fənn|dil" → suallar */
    private array $questions = [];

    /** @var array<string, int> "fənn|dil|açar" → mətn id-si (eyni mətn təkrar yaranmasın) */
    private array $passages = [];

    public array $stats = ['topics_created' => 0, 'questions_created' => 0, 'questions_updated' => 0];

    public function __construct(private readonly DemoQuestionFactory $factory) {}

    /**
     * @param  array<string, Subject>  $subjects  slug → fənn
     * @param  array<int, string>  $ruSubjects  rus sektoru sualı lazım olan fənlərin slug-ları
     * @param  array<string, array<int, string>>  $allowedTypes  fənn slug → icazəli sual tipləri
     */
    public function build(array $subjects, array $ruSubjects, array $allowedTypes = []): void
    {
        foreach (DemoTaxonomy::all() as $slug => $blocks) {
            $subject = $subjects[$slug] ?? null;

            if ($subject === null) {
                continue;
            }

            $topics = $this->syncTopics($subject, $blocks);
            $this->topics[$slug] = $topics;

            $languages = in_array($slug, $ruSubjects, true) ? ['az', 'ru'] : ['az'];

            foreach ($languages as $language) {
                $rows = $this->factory->build(
                    $slug,
                    $blocks,
                    $language,
                    self::QUESTIONS_PER_TOPIC,
                    $allowedTypes[$slug] ?? \App\Models\Question::TYPES,
                );
                $this->questions[$slug.'|'.$language] = $this->syncQuestions($subject, $topics, $rows, $language);
            }
        }
    }

    /**
     * Fənnin bir dildəki demo sualları — istənsə yalnız verilən rübdən.
     *
     * @return Collection<int, Question>
     */
    public function pool(string $subjectSlug, string $language, ?int $quarter = null): Collection
    {
        $pool = $this->questions[$subjectSlug.'|'.$language] ?? collect();

        if ($quarter === null) {
            return $pool;
        }

        $topicId = $this->topics[$subjectSlug][$quarter - 1]->id ?? null;

        return $pool->filter(fn (Question $question) => $question->topic_id === $topicId)->values();
    }

    /**
     * Mövzular: fənnə görə 4 ədəd, hər rübə bir.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, Topic>
     */
    private function syncTopics(Subject $subject, array $blocks): array
    {
        $topics = [];

        foreach ($blocks as $index => $block) {
            $slug = 'demo-'.Str::slug($block['name']).'-q'.($index + 1);

            $topic = Topic::firstOrNew(['subject_id' => $subject->id, 'slug' => $slug]);
            $exists = $topic->exists;

            $topic->fill([
                'name' => $block['name'],
                'quarter' => $index + 1,
                'order' => $index + 1,
                'is_active' => true,
                'is_demo' => true,
            ]);

            if ($topic->isDirty() || ! $exists) {
                $topic->save();
            }

            $this->stats['topics_created'] += $exists ? 0 : 1;
            $topics[] = $topic;
        }

        return $topics;
    }

    /**
     * Sual bankı: `source` açarı ilə idempotent.
     *
     * @param  array<int, Topic>  $topics
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, Question>
     */
    private function syncQuestions(Subject $subject, array $topics, array $rows, string $language): Collection
    {
        $existing = Question::with('options')
            ->where('is_demo', true)
            ->where('subject_id', $subject->id)
            ->where('language', $language)
            ->get()
            ->keyBy('source');

        $result = collect();

        foreach ($rows as $row) {
            $question = $existing->get($row['source']) ?? new Question;
            $isNew = ! $question->exists;

            $question->fill([
                'subject_id' => $subject->id,
                'topic_id' => $topics[$row['topic_index']]->id,
                'question_text' => $row['question_text'],
                'question_image' => $row['question_image'] ?? null,
                'question_image_alt' => $row['question_image_alt'] ?? null,
                'type' => $row['type'],
                // DİM alt növü: kodlaşdırılanda yoxlama qaydası, yazılıda məlumat
                'subtype' => $row['subtype'] ?? null,
                'language' => $language,
                'difficulty' => $row['difficulty'],
                'accepted_answers' => $row['accepted_answers'] ?? null,
                // Uyğunluq cütləri: sıra düzgün cavabdır (bax `App\Support\CodedAnswer`)
                'pairs' => $row['pairs'] ?? null,
                // Mətn/mənbə: eyni mətn bir neçə suala bağlanır
                'passage_id' => isset($row['passage'])
                    ? $this->passageId($row['passage'], $subject->id, $language)
                    : null,
                'explanation' => $row['explanation'] ?? null,
                'source' => $row['source'],
                'is_active' => true,
                'is_demo' => true,
            ]);

            if ($isNew || $question->isDirty()) {
                $question->save();
                $this->stats[$isNew ? 'questions_created' : 'questions_updated']++;
            }

            $this->syncOptions($question, $row['options'] ?? []);
            $result->push($question);
        }

        return $result;
    }

    /**
     * Mətn/mənbə sətri: fənn + dil + mövzu açarı ilə idempotentdir.
     *
     * Eyni mövzunun MƏTN və MƏNBƏ sualları eyni açarı göndərir, ona görə hər ikisi EYNİ
     * `passages` sətrinə bağlanır — "bir mətnə bir neçə sual" məhz budur.
     *
     * @param  array{key: string, title: string, body: string, source: string}  $passage
     */
    private function passageId(array $passage, int $subjectId, string $language): int
    {
        $cacheKey = $subjectId.'|'.$language.'|'.$passage['key'];

        if (isset($this->passages[$cacheKey])) {
            return $this->passages[$cacheKey];
        }

        $row = Passage::firstOrNew([
            'language' => $language,
            // Başlıq fənn və mövzu ilə unikallaşır: başqa fənnin eyni adlı mövzusu ayrı sətirdir
            'title' => $passage['title'].' #'.$subjectId,
        ]);

        $row->fill([
            'body' => $passage['body'],
            'source' => $passage['source'],
            'is_active' => true,
            'is_demo' => true,
        ]);

        if (! $row->exists || $row->isDirty()) {
            $row->save();
        }

        return $this->passages[$cacheKey] = $row->id;
    }

    /**
     * Variantlar yalnız DƏYİŞİBSƏ yenidən yazılır — əks halda hər işə salmada bütün
     * variantların ID-si dəyişərdi və köhnə cəhdlərin cavabları sınardı.
     *
     * @param  array<int, array{option_text: string, is_correct: bool}>  $options
     */
    private function syncOptions(Question $question, array $options): void
    {
        $current = $question->relationLoaded('options')
            ? $question->options
            : $question->options()->get();

        $same = $current->count() === count($options)
            && $current->values()->every(fn (QuestionOption $option, int $index) => $option->option_text === $options[$index]['option_text']
                && $option->is_correct === $options[$index]['is_correct']);

        if ($same) {
            return;
        }

        $question->options()->delete();

        foreach ($options as $index => $option) {
            $question->options()->create([
                'option_letter' => chr(65 + $index),
                'option_text' => $option['option_text'],
                'is_correct' => $option['is_correct'],
                'order' => $index + 1,
            ]);
        }

        $question->setRelation('options', $question->options()->get());
    }
}
