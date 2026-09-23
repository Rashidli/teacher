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
 * Ümumi kataloq: `/imtahanlar`.
 *
 * Kateqoriya səhifəsi ağacın bir düyününü göstərir, bu səhifə isə bütün dərc olunmuş
 * imtahanları — ən yenisindən başlayaraq. Filtr məntiqi ortaqdır (`CatalogFilters`).
 */
class ExamCatalogTest extends TestCase
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

    private function exam(array $attributes = [], ?Subject $subject = null): Exam
    {
        $exam = Exam::factory()->published()->create(array_merge([
            'category_id' => $this->ninth->id,
            'group_id' => $this->group->id,
        ], $attributes));

        if ($subject) {
            ExamSection::create([
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'question_count' => 5,
                'order' => 1,
            ]);
        }

        return $exam;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, string>
     */
    private function titles(array $query = [], string $path = '/imtahanlar'): array
    {
        $titles = [];

        $this->get($path.($query ? '?'.http_build_query($query) : ''))
            ->assertOk()
            ->assertInertia(function ($page) use (&$titles) {
                $titles = collect($page->toArray()['props']['exams'])->pluck('title')->all();
            });

        return $titles;
    }

    private function props(array $query = [], string $path = '/imtahanlar'): array
    {
        $props = [];

        $this->get($path.($query ? '?'.http_build_query($query) : ''))
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    public function test_the_newest_exams_come_first(): void
    {
        $this->exam(['title' => 'Köhnə', 'published_at' => now()->subDays(10)]);
        $this->exam(['title' => 'Ən yeni', 'published_at' => now()]);
        $this->exam(['title' => 'Ortada', 'published_at' => now()->subDays(3)]);

        $this->assertSame(['Ən yeni', 'Ortada', 'Köhnə'], $this->titles());
    }

    public function test_the_page_is_paginated(): void
    {
        Exam::factory()->published()->count(30)->create([
            'category_id' => $this->ninth->id,
            'group_id' => $this->group->id,
        ]);

        $first = $this->props();

        $this->assertCount(24, $first['exams']);
        $this->assertSame(30, $first['pagination']['total']);
        $this->assertSame(2, $first['pagination']['pages']);
        $this->assertNotNull($first['pagination']['next']);

        $second = $this->props(['sehife' => 2]);

        $this->assertCount(6, $second['exams']);
        $this->assertSame(2, $second['pagination']['page']);
    }

    public function test_only_published_and_active_exams_are_listed(): void
    {
        $this->exam(['title' => 'Görünən']);
        $this->exam(['title' => 'Qaralama', 'is_published' => false]);
        $this->exam(['title' => 'Deaktiv', 'is_active' => false]);

        $this->assertSame(['Görünən'], $this->titles());
    }

    public function test_the_category_filter_includes_the_subtree(): void
    {
        $this->exam(['title' => '9-cu sinif sınağı']);
        $this->exam(['title' => 'Ümumi məktəb sınağı', 'category_id' => $this->school->id]);

        $other = Category::create([
            'name' => 'Abituriyent', 'slug' => 'abituriyent', 'path' => 'abituriyent', 'has_exams' => true,
        ]);
        $this->exam(['title' => 'Abituriyent sınağı', 'category_id' => $other->id]);

        // Kök düyün alt düyünlərin imtahanlarını da yığır
        $this->assertEqualsCanonicalizing(
            ['9-cu sinif sınağı', 'Ümumi məktəb sınağı'],
            $this->titles(['kateqoriya' => $this->school->id]),
        );

        $this->assertSame(['9-cu sinif sınağı'], $this->titles(['kateqoriya' => $this->ninth->id]));
    }

    public function test_the_kind_and_quarter_filters_work(): void
    {
        $this->exam(['title' => 'Ümumi', 'kind' => Exam::KIND_GENERAL]);
        $this->exam(['title' => '1-ci rüb', 'kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 1]);
        $this->exam(['title' => '2-ci rüb', 'kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 2]);

        $this->assertEqualsCanonicalizing(
            ['1-ci rüb', '2-ci rüb'],
            $this->titles(['nov' => Exam::KIND_TOPIC_TRIAL]),
        );

        $this->assertSame(['2-ci rüb'], $this->titles(['nov' => Exam::KIND_TOPIC_TRIAL, 'rub' => 2]));

        // Rüb yalnız mövzu sınağı ilə birlikdə mənalıdır: təkbaşına nəzərə alınmır
        $this->assertCount(3, $this->titles(['rub' => 2]));
    }

    public function test_the_subject_filter_looks_at_exam_sections(): void
    {
        $math = Subject::create(['name' => 'Riyaziyyat', 'slug' => 'riyaziyyat', 'category' => 'technical']);
        $history = Subject::create(['name' => 'Tarix', 'slug' => 'tarix', 'category' => 'humanitarian']);

        $this->exam(['title' => 'Riyaziyyat sınağı'], $math);
        $this->exam(['title' => 'Tarix sınağı'], $history);

        $this->assertSame(['Riyaziyyat sınağı'], $this->titles(['fenn' => $math->id]));
    }

    public function test_the_price_filter_separates_free_and_paid(): void
    {
        $this->exam(['title' => 'Pulsuz', 'is_free' => true, 'price' => 0]);
        $this->exam(['title' => 'Ödənişli', 'is_free' => false, 'price' => 5]);

        $this->assertSame(['Pulsuz'], $this->titles(['qiymet' => 'pulsuz']));
        $this->assertSame(['Ödənişli'], $this->titles(['qiymet' => 'pullu']));
    }

    public function test_the_search_matches_the_title(): void
    {
        $this->exam(['title' => 'MİQ sınağı']);
        $this->exam(['title' => 'Buraxılış imtahanı']);

        $this->assertSame(['MİQ sınağı'], $this->titles(['axtar' => 'MİQ']));

        // Bir hərflik axtarış tətbiq edilmir: bütün kataloq qayıdardı
        $this->assertCount(2, $this->titles(['axtar' => 'M']));
    }

    public function test_filters_combine(): void
    {
        $this->exam(['title' => 'Uyğun', 'kind' => Exam::KIND_GENERAL, 'is_free' => true, 'price' => 0]);
        $this->exam(['title' => 'Növü uyğun deyil', 'kind' => Exam::KIND_SUBJECT, 'is_free' => true, 'price' => 0]);
        $this->exam(['title' => 'Qiyməti uyğun deyil', 'kind' => Exam::KIND_GENERAL, 'is_free' => false, 'price' => 5]);

        $this->assertSame(
            ['Uyğun'],
            $this->titles(['nov' => Exam::KIND_GENERAL, 'qiymet' => 'pulsuz', 'kateqoriya' => $this->school->id]),
        );
    }

    /** Sayğaclar əhatə üzrədir: seçilmiş çip digər ölçüləri daraltmır. */
    public function test_filter_options_carry_counts(): void
    {
        $this->exam(['kind' => Exam::KIND_GENERAL, 'is_free' => true, 'price' => 0]);
        $this->exam(['kind' => Exam::KIND_GENERAL, 'is_free' => false, 'price' => 5]);
        $this->exam(['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 3, 'is_free' => true, 'price' => 0]);

        $options = $this->props(['nov' => Exam::KIND_GENERAL])['filterOptions'];

        $this->assertSame(
            [['value' => Exam::KIND_GENERAL, 'count' => 2], ['value' => Exam::KIND_TOPIC_TRIAL, 'count' => 1]],
            $options['kinds'],
        );
        $this->assertSame([['value' => 3, 'count' => 1]], $options['quarters']);
        $this->assertSame(
            [['value' => 'pulsuz', 'count' => 2], ['value' => 'pullu', 'count' => 1]],
            $options['prices'],
        );
        $this->assertSame(
            [['value' => $this->school->id, 'name' => 'Orta məktəb', 'depth' => 0, 'count' => 3],
                ['value' => $this->ninth->id, 'name' => '9-cu sinif buraxılış', 'depth' => 1, 'count' => 3]],
            $options['categories'],
        );
    }

    public function test_the_sector_filter_applies(): void
    {
        $this->exam(['title' => 'Az imtahanı', 'sector' => Sector::AZ]);
        $this->exam(['title' => 'Ru imtahanı', 'sector' => Sector::RU]);

        $this->assertSame(['Az imtahanı'], $this->titles());
        $this->assertSame(['Ru imtahanı'], $this->titles([], '/ru/imtahanlar'));

        // Daxil olmuş şagird öz sektorunu görür, URL dilindən asılı olmayaraq
        $student = User::factory()->create(['sector' => Sector::RU]);
        $student->assignRole('student');

        $this->assertSame(['Ru imtahanı'], $this->actingAs($student)->titles());
    }

    /** Filtrli və səhifələnmiş ünvan indeksləşməsin: canonical filtrsiz səhifəyə göstərir. */
    public function test_the_canonical_ignores_filters_and_pages(): void
    {
        $this->exam();

        $seo = $this->props(['nov' => Exam::KIND_GENERAL, 'qiymet' => 'pulsuz', 'sehife' => 1])['seo'];

        $this->assertSame(url('/imtahanlar'), $seo['canonical']);
        $this->assertSame(
            ['az' => url('/imtahanlar'), 'ru' => url('/ru/imtahanlar')],
            $seo['alternates'],
        );
        $this->assertSame(url('/imtahanlar'), $seo['x_default']);

        $ruSeo = $this->props([], '/ru/imtahanlar')['seo'];

        $this->assertSame(url('/ru/imtahanlar'), $ruSeo['canonical']);
    }

    public function test_the_catalog_is_in_the_sitemap(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee(url('/imtahanlar'), escape: false);
        $response->assertSee(url('/ru/imtahanlar'), escape: false);
    }
}
