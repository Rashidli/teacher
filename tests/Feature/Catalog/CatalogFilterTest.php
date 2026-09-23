<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Subject;
use App\Models\User;
use App\Support\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kateqoriya səhifəsi əsas kataloqdur: düyünün və ALT düyünlərinin imtahanları çıxır,
 * filtrlər isə URL-də query kimi qalır (süzülmüş səhifə paylaşıla bilir).
 */
class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private Category $school;

    private Category $ninth;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create();

        $this->school = Category::create([
            'name' => 'Orta məktəb', 'slug' => 'mekteb', 'path' => 'mekteb', 'has_exams' => true,
        ]);

        $this->ninth = Category::create([
            'name' => '9-cu sinif buraxılış',
            'slug' => '9-cu-sinif-buraxilis',
            'path' => 'mekteb/9-cu-sinif-buraxilis',
            'parent_id' => $this->school->id,
            'has_exams' => true,
        ]);
    }

    private function exam(Category $category, array $attributes = []): Exam
    {
        return Exam::factory()->published()->create(array_merge([
            'category_id' => $category->id,
            'group_id' => $this->group->id,
        ], $attributes));
    }

    /**
     * Siyahıdakı imtahanların başlıqları, göründükləri sıra ilə.
     *
     * Kartda imtahanın ÖZ BAŞLIĞI göstərilmir (orada bölmə yolu və növ olur), ona görə
     * kartın slug-ından başlıq tapılır — testlərin gözləntiləri oxunaqlı qalsın.
     *
     * @return array<int, string>
     */
    private function titlesAt(string $path, array $query = []): array
    {
        $slugs = [];

        $this->get($path.($query ? '?'.http_build_query($query) : ''))
            ->assertOk()
            ->assertInertia(function ($page) use (&$slugs) {
                $slugs = collect($page->toArray()['props']['exams'])->pluck('slug');
            });

        $titles = Exam::whereIn('slug', $slugs)->pluck('title', 'slug');

        return $slugs->map(fn (string $slug) => $titles[$slug])->all();
    }

    /** Düyünün öz imtahanı öz səhifəsində görünür, qonşu düyününkü yox. */
    public function test_a_node_shows_its_own_exams(): void
    {
        $this->exam($this->ninth, ['title' => '9-cu sinif sınağı']);

        $eleventh = Category::create([
            'name' => '11-ci sinif buraxılış',
            'slug' => '11-ci-sinif-buraxilis',
            'path' => 'mekteb/11-ci-sinif-buraxilis',
            'parent_id' => $this->school->id,
        ]);
        $this->exam($eleventh, ['title' => '11-ci sinif sınağı']);

        $this->assertSame(['9-cu sinif sınağı'], $this->titlesAt('/mekteb/9-cu-sinif-buraxilis'));
    }

    /** Valideyn düyün alt düyünlərin imtahanlarını da yığır. */
    public function test_a_parent_node_collects_the_subtree(): void
    {
        $this->exam($this->ninth, ['title' => '9-cu sinif sınağı']);
        $this->exam($this->school, ['title' => 'Ümumi məktəb sınağı']);

        $this->assertEqualsCanonicalizing(
            ['9-cu sinif sınağı', 'Ümumi məktəb sınağı'],
            $this->titlesAt('/mekteb'),
        );
    }

    public function test_the_kind_filter_narrows_the_list(): void
    {
        $this->exam($this->ninth, ['title' => 'Ümumi sınaq', 'kind' => Exam::KIND_GENERAL]);
        $this->exam($this->ninth, ['title' => 'Rüb sınağı', 'kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 2]);

        $this->assertSame(
            ['Rüb sınağı'],
            $this->titlesAt('/mekteb/9-cu-sinif-buraxilis', ['nov' => Exam::KIND_TOPIC_TRIAL]),
        );
    }

    public function test_the_quarter_filter_applies_within_topic_trials(): void
    {
        $this->exam($this->ninth, ['title' => '1-ci rüb', 'kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 1]);
        $this->exam($this->ninth, ['title' => '2-ci rüb', 'kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 2]);

        $this->assertSame(
            ['2-ci rüb'],
            $this->titlesAt('/mekteb/9-cu-sinif-buraxilis', ['nov' => Exam::KIND_TOPIC_TRIAL, 'rub' => 2]),
        );
    }

    public function test_the_price_filter_separates_free_and_paid(): void
    {
        $this->exam($this->ninth, ['title' => 'Pulsuz sınaq', 'is_free' => true, 'price' => 0]);
        $this->exam($this->ninth, ['title' => 'Ödənişli sınaq', 'is_free' => false, 'price' => 10]);

        $this->assertSame(['Pulsuz sınaq'], $this->titlesAt('/mekteb/9-cu-sinif-buraxilis', ['qiymet' => 'pulsuz']));
        $this->assertSame(['Ödənişli sınaq'], $this->titlesAt('/mekteb/9-cu-sinif-buraxilis', ['qiymet' => 'pullu']));
    }

    /**
     * Fənn filtri imtahanın BÖLMƏLƏRİNƏ baxır: çoxfənli imtahan `exams.subject_id`
     * başqa fənn olsa da, içindəki hər fənnə görə tapılmalıdır.
     */
    public function test_the_subject_filter_looks_at_the_sections(): void
    {
        $maths = Subject::factory()->create(['name' => 'Riyaziyyat']);
        $physics = Subject::factory()->create(['name' => 'Fizika']);

        // İki bölməli imtahan: exams.subject_id = Riyaziyyat, bölmələr = Riyaziyyat + Fizika
        $multi = $this->exam($this->ninth, ['title' => 'I qrup sınağı', 'subject_id' => $maths->id]);
        ExamSection::create([
            'exam_id' => $multi->id,
            'subject_id' => $physics->id,
            'question_count' => 25,
            'max_score' => 100,
            'order' => 2,
        ]);

        // Yalnız riyaziyyat bölməsi olan imtahan
        $this->exam($this->ninth, ['title' => 'Riyaziyyat sınağı', 'subject_id' => $maths->id]);

        $this->assertSame(
            ['I qrup sınağı'],
            $this->titlesAt('/mekteb/9-cu-sinif-buraxilis', ['fenn' => $physics->id]),
            'Çoxfənli imtahan ikinci fənninə görə tapılmalıdır',
        );

        $this->assertEqualsCanonicalizing(
            ['I qrup sınağı', 'Riyaziyyat sınağı'],
            $this->titlesAt('/mekteb/9-cu-sinif-buraxilis', ['fenn' => $maths->id]),
        );
    }

    /** Filtr variantları sayğaclarla gəlir və yalnız mövcud olanlar göstərilir. */
    public function test_the_filter_options_carry_counts(): void
    {
        $this->exam($this->ninth, ['kind' => Exam::KIND_GENERAL, 'is_free' => true]);
        $this->exam($this->ninth, ['kind' => Exam::KIND_GENERAL, 'is_free' => false, 'price' => 5]);
        $this->exam($this->ninth, ['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 3, 'is_free' => true]);

        $this->get('/mekteb/9-cu-sinif-buraxilis')
            ->assertInertia(fn ($page) => $page
                ->where('filterOptions.kinds.0.value', Exam::KIND_GENERAL)
                ->where('filterOptions.kinds.0.count', 2)
                ->where('filterOptions.kinds.1.value', Exam::KIND_TOPIC_TRIAL)
                ->where('filterOptions.kinds.1.count', 1)
                ->where('filterOptions.prices.0.count', 2));
    }

    /** Yanlış filtr dəyəri siyahını pozmur, sadəcə nəzərə alınmır. */
    public function test_an_unknown_filter_value_is_ignored(): void
    {
        $this->exam($this->ninth, ['title' => 'Sınaq']);

        $this->assertSame(['Sınaq'], $this->titlesAt('/mekteb/9-cu-sinif-buraxilis', ['nov' => 'yalan']));
    }

    /** Seçim səhifəyə geri ötürülür ki, panel cari vəziyyəti göstərsin. */
    public function test_the_active_filters_are_returned_to_the_page(): void
    {
        $this->exam($this->ninth, ['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 2]);

        $this->get('/mekteb/9-cu-sinif-buraxilis?nov=topic_trial&rub=2&qiymet=pulsuz')
            ->assertInertia(fn ($page) => $page
                ->where('filters.nov', Exam::KIND_TOPIC_TRIAL)
                ->where('filters.rub', 2)
                ->where('filters.qiymet', 'pulsuz')
                ->where('filters.fenn', null));
    }

    /** Kataloq şagirdin sektoruna görə süzülür. */
    public function test_the_catalog_respects_the_sector(): void
    {
        $this->exam($this->ninth, ['title' => 'Azərbaycan sınağı', 'sector' => Sector::AZ]);
        $this->exam($this->ninth, ['title' => 'Rus sınağı', 'sector' => Sector::RU]);

        $this->assertSame(['Azərbaycan sınağı'], $this->titlesAt('/mekteb/9-cu-sinif-buraxilis'));

        $student = User::factory()->student()->create(['sector' => Sector::RU]);

        $this->actingAs($student);
        $this->assertSame(['Rus sınağı'], $this->titlesAt('/mekteb/9-cu-sinif-buraxilis'));
    }

    /** Kartlar giriş formasına yox, ictimai imtahan səhifəsinə aparır. */
    public function test_the_cards_link_to_the_public_exam_page(): void
    {
        $exam = $this->exam($this->ninth, ['title' => 'Sınaq']);

        $this->get('/mekteb/9-cu-sinif-buraxilis')
            ->assertInertia(fn ($page) => $page
                ->where('exams.0.url', $exam->publicUrl())
                ->where('exams.0.slug', $exam->slug));
    }
}
