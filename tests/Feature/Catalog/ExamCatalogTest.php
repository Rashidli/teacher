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
 * İki görünüş: filtr/axtarış seçilməyibsə bölmələr üzrə qruplaşdırılmış (`grouped`),
 * seçiləndə səhifələnən düz siyahı (`list`). Sıralama görünüşü dəyişmir — yalnız
 * bölmələrin içindəki sıranı dəyişir.
 *
 * Kartda imtahanın öz başlığı göstərilmir (bölmə yolu + növ olur), ona görə yoxlamalar
 * slug üzrədir.
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
            'name' => 'Orta məktəb', 'slug' => 'mekteb', 'path' => 'mekteb',
            'has_exams' => true, 'color' => '#2440A0',
        ]);

        $this->ninth = Category::create([
            'name' => '9-cu sinif buraxılış',
            'slug' => '9-cu-sinif-buraxilis',
            'path' => 'mekteb/9-cu-sinif-buraxilis',
            'parent_id' => $this->school->id,
            'has_exams' => true,
        ]);
    }

    private function exam(string $slug, array $attributes = [], ?Subject $subject = null): Exam
    {
        $exam = Exam::factory()->published()->create(array_merge([
            'slug' => $slug,
            'title' => $slug,
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

    /**
     * Siyahıdakı (və ya qruplardakı) imtahanların slug-ları, göründükləri sıra ilə.
     *
     * @return array<int, string>
     */
    private function slugs(array $query = [], string $path = '/imtahanlar'): array
    {
        $props = $this->props($query, $path);

        return $props['mode'] === 'grouped'
            ? collect($props['groups'])->flatMap(fn ($group) => collect($group['exams'])->pluck('slug'))->all()
            : collect($props['exams'])->pluck('slug')->all();
    }

    /* ------------------------------------------------------------- görünüş */

    /** Defolt görünüş: kök bölmələr üzrə qruplar, hər birində say və "hamısına bax" keçidi. */
    public function test_the_default_view_is_grouped_by_root_category(): void
    {
        $this->exam('a');
        $this->exam('b');

        $props = $this->props();

        $this->assertSame('grouped', $props['mode']);
        $this->assertNull($props['pagination']);
        $this->assertCount(1, $props['groups']);

        $group = $props['groups'][0];
        $this->assertSame('Orta məktəb', $group['name']);
        $this->assertSame('#2440A0', $group['color']);
        $this->assertSame(2, $group['total']);
        $this->assertSame(url('/mekteb'), $group['url']);
    }

    /** Bölmədə çox imtahan olsa da kartların sayı məhdudlanır — biri səhifəni tutmur. */
    public function test_a_group_shows_only_the_first_few_exams(): void
    {
        Exam::factory()->published()->count(9)->create([
            'category_id' => $this->ninth->id,
            'group_id' => $this->group->id,
        ]);

        $group = $this->props()['groups'][0];

        $this->assertSame(9, $group['total']);
        $this->assertCount(4, $group['exams']);
    }

    /** Filtr seçiləndə düz siyahıya keçilir. */
    public function test_a_filter_switches_to_the_list_view(): void
    {
        $this->exam('umumi', ['kind' => Exam::KIND_GENERAL]);
        $this->exam('movzu', ['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 2]);

        $props = $this->props(['nov' => Exam::KIND_GENERAL]);

        $this->assertSame('list', $props['mode']);
        $this->assertSame([], $props['groups']);
        $this->assertSame(1, $props['pagination']['total']);
        $this->assertSame(['umumi'], collect($props['exams'])->pluck('slug')->all());
    }

    /** Axtarış da siyahıya keçirir. */
    public function test_a_search_switches_to_the_list_view(): void
    {
        $this->exam('miq-sinagi', ['title' => 'MİQ sınağı']);
        $this->exam('buraxilis', ['title' => 'Buraxılış imtahanı']);

        $props = $this->props(['axtar' => 'MİQ']);

        $this->assertSame('list', $props['mode']);
        $this->assertSame(['miq-sinagi'], collect($props['exams'])->pluck('slug')->all());
    }

    /** SIRALAMA görünüşü dəyişmir: qruplar qalır, yalnız içindəki sıra dəyişir. */
    public function test_sorting_alone_keeps_the_grouped_view(): void
    {
        $this->exam('ucuz', ['is_free' => false, 'price' => 3]);
        $this->exam('baha', ['is_free' => false, 'price' => 20]);
        $this->exam('pulsuz', ['is_free' => true, 'price' => 0]);

        $props = $this->props(['sirala' => 'ucuz']);

        $this->assertSame('grouped', $props['mode']);
        $this->assertSame('ucuz', $props['sort']);
        $this->assertSame(['pulsuz', 'ucuz', 'baha'], collect($props['groups'][0]['exams'])->pluck('slug')->all());
    }

    /* ------------------------------------------------------------ sıralama */

    public function test_the_newest_exams_come_first_by_default(): void
    {
        $this->exam('kohne', ['published_at' => now()->subDays(10)]);
        $this->exam('yeni', ['published_at' => now()]);
        $this->exam('ortada', ['published_at' => now()->subDays(3)]);

        $this->assertSame(['yeni', 'ortada', 'kohne'], $this->slugs());
    }

    public function test_free_exams_can_be_sorted_first(): void
    {
        $this->exam('pullu', ['is_free' => false, 'price' => 9]);
        $this->exam('pulsuz', ['is_free' => true, 'price' => 0]);

        $this->assertSame(['pulsuz', 'pullu'], $this->slugs(['sirala' => 'pulsuz']));
    }

    public function test_exams_can_be_sorted_by_price(): void
    {
        $this->exam('ucuz', ['is_free' => false, 'price' => 3]);
        $this->exam('orta', ['is_free' => false, 'price' => 7]);
        $this->exam('baha', ['is_free' => false, 'price' => 20]);

        $this->assertSame(['ucuz', 'orta', 'baha'], $this->slugs(['sirala' => 'ucuz']));
        $this->assertSame(['baha', 'orta', 'ucuz'], $this->slugs(['sirala' => 'baha']));
    }

    /** Tanınmayan sıralama defolta düşür, səhifə sınmır. */
    public function test_an_unknown_sort_falls_back_to_the_default(): void
    {
        $this->exam('a');

        $this->assertSame('yeni', $this->props(['sirala' => 'hech-ne'])['sort']);
    }

    /* -------------------------------------------------------------- filtrlər */

    public function test_the_page_is_paginated_in_the_list_view(): void
    {
        Exam::factory()->published()->count(30)->create([
            'category_id' => $this->ninth->id,
            'group_id' => $this->group->id,
            'kind' => Exam::KIND_GENERAL,
        ]);

        $first = $this->props(['nov' => Exam::KIND_GENERAL]);

        $this->assertCount(24, $first['exams']);
        $this->assertSame(30, $first['pagination']['total']);
        $this->assertSame(2, $first['pagination']['pages']);

        $second = $this->props(['nov' => Exam::KIND_GENERAL, 'sehife' => 2]);

        $this->assertCount(6, $second['exams']);
        $this->assertSame(2, $second['pagination']['page']);
    }

    public function test_only_published_and_active_exams_are_listed(): void
    {
        $this->exam('gorunen');
        $this->exam('qaralama', ['is_published' => false]);
        $this->exam('deaktiv', ['is_active' => false]);

        $this->assertSame(['gorunen'], $this->slugs());
    }

    public function test_the_category_filter_includes_the_subtree(): void
    {
        $this->exam('doqquzuncu');
        $this->exam('mekteb-umumi', ['category_id' => $this->school->id]);

        $other = Category::create([
            'name' => 'Abituriyent', 'slug' => 'abituriyent', 'path' => 'abituriyent',
            'has_exams' => true, 'color' => '#C8354E',
        ]);
        $this->exam('abituriyent-sinagi', ['category_id' => $other->id]);

        $this->assertEqualsCanonicalizing(
            ['doqquzuncu', 'mekteb-umumi'],
            $this->slugs(['kateqoriya' => $this->school->id]),
        );

        $this->assertSame(['doqquzuncu'], $this->slugs(['kateqoriya' => $this->ninth->id]));
    }

    public function test_the_kind_and_quarter_filters_work(): void
    {
        $this->exam('umumi', ['kind' => Exam::KIND_GENERAL]);
        $this->exam('rub-1', ['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 1]);
        $this->exam('rub-2', ['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 2]);

        $this->assertEqualsCanonicalizing(['rub-1', 'rub-2'], $this->slugs(['nov' => Exam::KIND_TOPIC_TRIAL]));
        $this->assertSame(['rub-2'], $this->slugs(['nov' => Exam::KIND_TOPIC_TRIAL, 'rub' => 2]));

        // Rüb yalnız mövzu sınağı ilə birlikdə mənalıdır: təkbaşına nəzərə alınmır
        $this->assertCount(3, $this->slugs(['rub' => 2]));
    }

    public function test_the_subject_filter_looks_at_exam_sections(): void
    {
        $math = Subject::create(['name' => 'Riyaziyyat', 'slug' => 'riyaziyyat', 'category' => 'technical']);
        $history = Subject::create(['name' => 'Tarix', 'slug' => 'tarix', 'category' => 'humanitarian']);

        $this->exam('riyaziyyat-sinagi', [], $math);
        $this->exam('tarix-sinagi', [], $history);

        $this->assertSame(['riyaziyyat-sinagi'], $this->slugs(['fenn' => $math->id]));
    }

    public function test_the_price_filter_separates_free_and_paid(): void
    {
        $this->exam('pulsuz', ['is_free' => true, 'price' => 0]);
        $this->exam('pullu', ['is_free' => false, 'price' => 5]);

        $this->assertSame(['pulsuz'], $this->slugs(['qiymet' => 'pulsuz']));
        $this->assertSame(['pullu'], $this->slugs(['qiymet' => 'pullu']));
    }

    public function test_filters_combine(): void
    {
        $this->exam('uygun', ['kind' => Exam::KIND_GENERAL, 'is_free' => true, 'price' => 0]);
        $this->exam('novu-uygun-deyil', ['kind' => Exam::KIND_SUBJECT, 'is_free' => true, 'price' => 0]);
        $this->exam('qiymeti-uygun-deyil', ['kind' => Exam::KIND_GENERAL, 'is_free' => false, 'price' => 5]);

        $this->assertSame(
            ['uygun'],
            $this->slugs(['nov' => Exam::KIND_GENERAL, 'qiymet' => 'pulsuz', 'kateqoriya' => $this->school->id]),
        );
    }

    /** Tək filtri silmək: digər seçimlər yerində qalır. */
    public function test_removing_one_filter_keeps_the_others(): void
    {
        $this->exam('uygun', ['kind' => Exam::KIND_GENERAL, 'is_free' => true, 'price' => 0]);
        $this->exam('pullu-umumi', ['kind' => Exam::KIND_GENERAL, 'is_free' => false, 'price' => 5]);

        // Qiymət filtri silinəndə növ qalır: hər iki ümumi sınaq çıxır
        $props = $this->props(['nov' => Exam::KIND_GENERAL]);

        $this->assertSame(Exam::KIND_GENERAL, $props['filters']['nov']);
        $this->assertNull($props['filters']['qiymet']);
        $this->assertCount(2, $props['exams']);
    }

    /** Sayğaclar əhatə üzrədir: seçilmiş çip digər ölçüləri daraltmır. */
    public function test_filter_options_carry_counts(): void
    {
        $this->exam('a', ['kind' => Exam::KIND_GENERAL, 'is_free' => true, 'price' => 0]);
        $this->exam('b', ['kind' => Exam::KIND_GENERAL, 'is_free' => false, 'price' => 5]);
        $this->exam('c', ['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 3, 'is_free' => true, 'price' => 0]);

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

        // Kateqoriya filtri ikisəviyyəlidir: kök + övladlar
        $this->assertSame(
            [[
                'value' => $this->school->id,
                'name' => 'Orta məktəb',
                'color' => '#2440A0',
                'count' => 3,
                'children' => [[
                    'value' => $this->ninth->id,
                    'name' => '9-cu sinif buraxılış',
                    'count' => 3,
                ]],
            ]],
            $options['categories'],
        );
    }

    /* ------------------------------------------------------------ sektor və SEO */

    public function test_the_sector_filter_applies(): void
    {
        $this->exam('az-imtahani', ['sector' => Sector::AZ]);
        $this->exam('ru-imtahani', ['sector' => Sector::RU]);

        $this->assertSame(['az-imtahani'], $this->slugs());
        $this->assertSame(['ru-imtahani'], $this->slugs([], '/ru/imtahanlar'));

        // Daxil olmuş şagird öz sektorunu görür, URL dilindən asılı olmayaraq
        $student = User::factory()->create(['sector' => Sector::RU]);
        $student->assignRole('student');

        $this->assertSame(['ru-imtahani'], $this->actingAs($student)->slugs());
    }

    /** Filtrli və səhifələnmiş ünvan indeksləşməsin: canonical filtrsiz səhifəyə göstərir. */
    public function test_the_canonical_ignores_filters_and_pages(): void
    {
        $this->exam('a');

        $seo = $this->props(['nov' => Exam::KIND_GENERAL, 'qiymet' => 'pulsuz', 'sirala' => 'ucuz', 'sehife' => 1])['seo'];

        $this->assertSame(url('/imtahanlar'), $seo['canonical']);
        $this->assertSame(
            ['az' => url('/imtahanlar'), 'ru' => url('/ru/imtahanlar')],
            $seo['alternates'],
        );
        $this->assertSame(url('/imtahanlar'), $seo['x_default']);

        $this->assertSame(url('/ru/imtahanlar'), $this->props([], '/ru/imtahanlar')['seo']['canonical']);
    }

    public function test_the_catalog_is_in_the_sitemap(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee(url('/imtahanlar'), escape: false);
        $response->assertSee(url('/ru/imtahanlar'), escape: false);
    }

    /** Kartda bölmə yolu və rəngi olur — imtahanın öz başlığı təkrarlanmır. */
    public function test_a_card_carries_the_category_trail_and_colour(): void
    {
        $this->exam('a', ['kind' => Exam::KIND_TOPIC_TRIAL, 'quarter' => 3]);

        $card = $this->props()['groups'][0]['exams'][0];

        $this->assertSame(
            ['root' => 'Orta məktəb', 'leaf' => '9-cu sinif buraxılış', 'color' => '#2440A0'],
            $card['trail'],
        );
        $this->assertSame(Exam::KIND_TOPIC_TRIAL, $card['kind']);
        $this->assertSame(3, $card['quarter']);
        $this->assertArrayNotHasKey('title', $card);
    }
}
