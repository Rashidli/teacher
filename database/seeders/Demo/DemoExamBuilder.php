<?php

namespace Database\Seeders\Demo;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Tag;
use App\Support\QuestionTypes;
use Illuminate\Support\Collection;

/**
 * Demo imtahanlarını, bölmələrini və sual bağlantılarını qurur.
 *
 * İdempotentlik `exams.slug` üzərindədir: slug kateqoriya yolundan və imtahanın nömrəsindən
 * hesablanır (`demo-abituriyent-1-ci-qrup-rk-2`), ona görə ikinci işə salmada eyni sətir
 * yenilənir. Silinmiş (soft delete) demo imtahan da tapılır və geri qaytarılır.
 */
class DemoExamBuilder
{
    /** Növün başlıqda görünən adı */
    private const KIND_LABELS = [
        'az' => [
            Exam::KIND_GENERAL => 'ümumi sınaq',
            Exam::KIND_TOPIC_TRIAL => 'mövzu sınağı',
            Exam::KIND_SUBJECT => 'fənn sınağı',
            Exam::KIND_PRACTICE => 'məşq testi',
        ],
        'ru' => [
            Exam::KIND_GENERAL => 'общий пробный',
            Exam::KIND_TOPIC_TRIAL => 'тематический пробный',
            Exam::KIND_SUBJECT => 'предметный пробный',
            Exam::KIND_PRACTICE => 'тренировочный тест',
        ],
    ];

    public array $stats = ['exams_created' => 0, 'exams_updated' => 0];

    /** @var array<int, Exam> */
    public array $exams = [];

    /**
     * @param  array<string, Subject>  $subjects  slug → fənn
     */
    /** @var array<int, int> sinif nömrəsi → tag id */
    private array $gradeTags = [];

    public function __construct(
        private readonly DemoBankBuilder $bank,
        private readonly array $subjects,
        private readonly ?int $createdBy,
    ) {
        // Sinif etiketləri `TagSeeder`-dən gəlir; yoxdursa etiket bağlanmır
        $this->gradeTags = Tag::grades()->pluck('id', 'order')->all();
    }

    /** @param  array<string, Category>  $categories  path → kateqoriya */
    public function build(array $categories): void
    {
        foreach (DemoCatalog::nodes() as $nodeIndex => $node) {
            $category = $categories[$node['path']] ?? null;

            if ($category === null) {
                continue;
            }

            if (($node['az'] ?? true) && ($node['sets'] ?? []) !== []) {
                $this->buildSector($category, $node, $nodeIndex, 'az', $node['sets']);
            }

            // Rus sektoru yalnız kateqoriya onu dəstəkləyəndə açılır
            if ($category->ru_enabled && ($node['ru_sets'] ?? []) !== []) {
                $this->buildSector($category, $node, $nodeIndex, 'ru', $node['ru_sets']);
            }
        }
    }

    /** @param  array<int, array<int, string>>  $sets */
    private function buildSector(Category $category, array $node, int $nodeIndex, string $sector, array $sets): void
    {
        foreach (DemoCatalog::KINDS as $i => $kind) {
            $set = $sets[$i % count($sets)];
            $sectionSubjects = $this->sectionSubjects($set, $kind);

            if ($sectionSubjects === []) {
                continue;
            }

            $quarter = $kind === Exam::KIND_TOPIC_TRIAL ? (($nodeIndex + $i) % 4) + 1 : null;
            $isFree = $i % 2 === 0;

            $exam = $this->upsertExam($category, $node, $nodeIndex, $sector, $kind, $i, $quarter, $isFree, $sectionSubjects);

            if ($exam !== null) {
                $this->exams[] = $exam;
            }
        }
    }

    /**
     * Bölmələrin fənləri. Ümumi və mövzu sınağı bütün dəsti götürür; fənn sınağı birinci,
     * məşq testi isə sonuncu fənni — beləcə eyni düyündə iki tək-fənli imtahan fərqli
     * fənnə düşür və kataloqun fənn filtri daha çox variant göstərir.
     *
     * @param  array<int, string>  $set
     * @return array<int, Subject>
     */
    private function sectionSubjects(array $set, string $kind): array
    {
        $slugs = match ($kind) {
            Exam::KIND_SUBJECT => [$set[0]],
            Exam::KIND_PRACTICE => [$set[count($set) - 1]],
            default => $set,
        };

        $subjects = [];

        foreach ($slugs as $slug) {
            if (isset($this->subjects[$slug])) {
                $subjects[] = $this->subjects[$slug];
            }
        }

        return $subjects;
    }

    /** @param  array<int, Subject>  $sectionSubjects */
    private function upsertExam(
        Category $category,
        array $node,
        int $nodeIndex,
        string $sector,
        string $kind,
        int $i,
        ?int $quarter,
        bool $isFree,
        array $sectionSubjects,
    ): ?Exam {
        $perSection = $this->questionsPerSection(count($sectionSubjects), $kind);
        $picks = $this->pickQuestions(
            $sectionSubjects, $sector, $quarter, $perSection, $nodeIndex, $i,
            QuestionTypes::forCategory($category),
        );

        // Bank çatmırsa imtahan qurulmur — yarımçıq imtahan kataloqa düşməsin
        if ($picks === null) {
            return null;
        }

        $slug = $this->slug($node['path'], $sector, $i);
        $exam = Exam::withTrashed()->firstOrNew(['slug' => $slug]);
        $isNew = ! $exam->exists;

        $exam->fill([
            'created_by' => $this->createdBy,
            'teacher_id' => null,
            'subject_id' => $sectionSubjects[0]->id,
            // DİM bal qrupu yalnız abituriyent kateqoriyalarında var; qalanlarında NULL
            'group_id' => $category->group_id,
            'category_id' => $category->id,
            'kind' => $kind,
            'sector' => $sector,
            'quarter' => $quarter,
            'is_cumulative' => false,
            'title' => $this->title($category, $node, $sector, $kind, $quarter, $sectionSubjects),
            // İzah boş qalır: şagird tərəfdə "demo" izahı görünməməlidir
            'description' => null,
            'duration_minutes' => $this->duration($node['duration'], $kind),
            'options_per_question' => $node['options'],
            'is_free' => $isFree,
            'price' => $isFree ? 0 : DemoCatalog::PRICES[($nodeIndex + $i) % count(DemoCatalog::PRICES)],
            'is_active' => true,
            'is_published' => true,
            'published_at' => $exam->published_at ?? now(),
            'created_by_admin' => true,
            'is_demo' => true,
        ]);

        if ($exam->trashed()) {
            $exam->deleted_at = null;
        }

        if ($isNew || $exam->isDirty()) {
            $exam->save();
            $this->stats[$isNew ? 'exams_created' : 'exams_updated']++;
        }

        $this->syncSections($exam, $sectionSubjects, $category, $picks, $perSection);

        // Sinif etiketləri: buraxılış 9/11, abituriyent 11 və s.
        $exam->tags()->sync(array_values(array_filter(array_map(
            fn (int $grade) => $this->gradeTags[$grade] ?? null,
            $node['grades'] ?? [],
        ))));

        return $exam;
    }

    /**
     * Bölmələr və sual bağlantıları.
     *
     * @param  array<int, Subject>  $subjects
     * @param  array<int, Collection<int, Question>>  $picks
     */
    private function syncSections(Exam $exam, array $subjects, Category $category, array $picks, int $perSection): void
    {
        $keep = [];
        $pivot = [];

        foreach ($subjects as $index => $subject) {
            $section = ExamSection::firstOrNew(['exam_id' => $exam->id, 'subject_id' => $subject->id]);

            $section->fill([
                'title' => $subject->name,
                'question_count' => $picks[$index]->count(),
                // Bal kateqoriyadan gəlir (abituriyentdə bal matrisindən); yoxdursa
                // bölmələr arasında bərabər bölünür
                'max_score' => $category->maxScoreFor($subject) ?? round(100 / count($subjects), 2),
                'order' => $index + 1,
            ]);

            if (! $section->exists || $section->isDirty()) {
                $section->save();
            }

            $keep[] = $section->id;

            foreach ($picks[$index] as $order => $question) {
                $pivot[$question->id] = ['section_id' => $section->id, 'order' => $order + 1];
            }
        }

        // Plandan çıxmış bölmələr silinir (onların pivot sətirləri də gedir)
        $exam->sections()->whereNotIn('id', $keep)->delete();
        $exam->questions()->sync($pivot);
    }

    /**
     * Hər bölmədən neçə sual götürülür. Cəm 8–12 arasında qalır.
     *
     * Mövzu sınağında hovuz bir rüblə məhdudlanır (mövzu üzrə 8 sual), ona görə tək-fənli
     * rüb sınağı hovuzun hamısını götürür.
     */
    private function questionsPerSection(int $sections, string $kind): int
    {
        if ($sections >= 3) {
            return 4;
        }

        if ($sections === 2) {
            return 5;
        }

        return match ($kind) {
            Exam::KIND_TOPIC_TRIAL, Exam::KIND_PRACTICE => 8,
            default => 10,
        };
    }

    /**
     * Bölmələrin sualları. Hər imtahan hovuzdan başqa yerdən başlayır ki, eyni düyündəki
     * imtahanlar eyni sual dəstini göstərməsin.
     *
     * @param  array<int, Subject>  $subjects
     * @return array<int, Collection<int, Question>>|null bank çatmırsa null
     */
    private function pickQuestions(array $subjects, string $sector, ?int $quarter, int $perSection, int $nodeIndex, int $i, array $allowedTypes): ?array
    {
        $picks = [];

        foreach ($subjects as $index => $subject) {
            // İmtahan növündə icazəsiz tiplər hovuzdan çıxır (sürücülükdə açıq sual olmur)
            $pool = $this->bank->pool($subject->slug, $sector, $quarter)
                ->filter(fn (Question $question) => in_array($question->type, $allowedTypes, true))
                ->values();

            if ($pool->count() < $perSection) {
                return null;
            }

            $offset = ($nodeIndex * 3 + $i * 5 + $index) % $pool->count();

            // Hovuz dairəvi oxunur: sonundan başlasaq da lazımi qədər sual yığılır
            $picks[$index] = $pool->concat($pool)->slice($offset, $perSection)->values();
        }

        return $picks;
    }

    /** @param  array<int, Subject>  $subjects */
    private function title(Category $category, array $node, string $sector, string $kind, ?int $quarter, array $subjects): string
    {
        $name = $sector === 'ru' ? $category->localized('name', 'ru') : $node['label'];
        $label = self::KIND_LABELS[$sector][$kind];

        $suffix = '';

        if ($quarter !== null) {
            $suffix = $sector === 'ru' ? " ({$quarter}-я четверть)" : " ({$quarter}-ci rüb)";
        } elseif (in_array($kind, [Exam::KIND_SUBJECT, Exam::KIND_PRACTICE], true)) {
            $suffix = ': '.$subjects[0]->name;
        }

        /*
         * Başlıqda "demo" sözü yoxdur: nümunə məzmun şagird tərəfdə real məzmundan
         * seçilməməlidir. Ayırd etmə `exams.is_demo` bayrağı ilədir (admin paneldə nişan,
         * `demo:clear` üçün açar). Slug-dakı `demo-` hissəsi isə dəyişmir — mövcud
         * ünvanlar qırılmasın.
         */
        return "{$name} — {$label}{$suffix}";
    }

    /** Fənn sınağı və məşq testi qısa olur — kataloqda müddət fərqi də görünsün */
    private function duration(int $base, string $kind): int
    {
        return match ($kind) {
            Exam::KIND_SUBJECT => max(30, (int) round($base / 2)),
            Exam::KIND_PRACTICE => max(15, (int) round($base / 4)),
            default => $base,
        };
    }

    private function slug(string $path, string $sector, int $i): string
    {
        $flat = str_replace('/', '-', $path);
        $prefix = $sector === 'az' ? 'demo' : 'demo-'.$sector;

        return substr("{$prefix}-{$flat}-".($i + 1), 0, 180);
    }
}
