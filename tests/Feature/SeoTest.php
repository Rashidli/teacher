<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\SubjectGroupScoreSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * SEO: rusca ünvanlar, canonical/hreflang, JSON-LD və sitemap.
 *
 * Mövcud Azərbaycan ünvanları dəyişmir (`mekteb`, `miq`, `suruculuk-imtahani`) —
 * yalnız rus dilində ayrıca yol yaranır və köhnə rus ünvanı 301 ilə ona yönləndirilir.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(SubjectSeeder::class);
        $this->seed(GroupSeeder::class);
        $this->seed(SubjectGroupScoreSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    // ---- Rusca ünvanlar ----

    public function test_the_seeder_builds_russian_paths(): void
    {
        $this->assertSame('abiturient', Category::where('path', 'abituriyent')->value('ru_path'));
        $this->assertSame(
            'abiturient/1-ya-gruppa/rk',
            Category::where('path', 'abituriyent/1-ci-qrup/rk')->value('ru_path')
        );

        // Qısa ünvanlar hər iki dildə qorunur
        $this->assertSame('miq', Category::where('path', 'miq')->value('ru_path'));
    }

    public function test_the_russian_path_opens_the_page(): void
    {
        $this->get('/ru/abiturient/1-ya-gruppa')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('category.name', 'I qrup'));
    }

    public function test_the_azerbaijani_path_under_ru_redirects_permanently(): void
    {
        $this->get('/ru/abituriyent/1-ci-qrup')
            ->assertStatus(301)
            ->assertRedirect(url('ru/abiturient/1-ya-gruppa'));
    }

    /** Azərbaycan səhifəsi rusca yolu tanımır: iki dildə iki ayrı ünvan. */
    public function test_the_russian_path_is_not_served_without_the_prefix(): void
    {
        $this->get('/abiturient')->assertNotFound();
        $this->get('/abituriyent')->assertOk();
    }

    public function test_canonical_and_hreflang_use_the_translated_path(): void
    {
        $this->get('/ru/abiturient')
            ->assertInertia(fn ($page) => $page
                ->where('seo.canonical', url('ru/abiturient'))
                ->where('seo.alternates.az', url('abituriyent'))
                ->where('seo.alternates.ru', url('ru/abiturient'))
                ->where('seo.x_default', url('abituriyent')));

        $this->get('/abituriyent')
            ->assertInertia(fn ($page) => $page
                ->where('seo.canonical', url('abituriyent'))
                ->where('seo.alternates.ru', url('ru/abiturient')));
    }

    /** Səhifədəki keçidlər də tərcümə olunmuş ünvanla gedir (qarışıq dil olmasın). */
    public function test_links_on_the_russian_page_use_russian_paths(): void
    {
        $this->get('/ru/abiturient')
            ->assertInertia(fn ($page) => $page
                ->where('category.url', url('ru/abiturient'))
                ->where('children.0.url', url('ru/abiturient/1-y-etap')));
    }

    // ---- JSON-LD ----

    public function test_the_page_contains_a_breadcrumb_json_ld(): void
    {
        $html = $this->get('/abituriyent/1-ci-qrup')->assertOk()->getContent();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString(url('abituriyent/1-ci-qrup'), $html);
    }

    public function test_pages_without_structured_data_do_not_emit_json_ld(): void
    {
        $this->assertStringNotContainsString('application/ld+json', $this->get('/qaydalar')->getContent());
    }

    // ---- Sitemap ----

    public function test_the_sitemap_lists_both_languages(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('abituriyent').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('ru/abiturient').'</loc>', $xml);
        $this->assertStringContainsString('hreflang="x-default"', $xml);

        // Deaktiv kateqoriya sitemap-a düşmür
        $this->assertStringNotContainsString(url('diger'), $xml);
    }

    public function test_the_sitemap_is_valid_xml(): void
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        $this->assertNotFalse($xml, 'sitemap.xml düzgün XML olmalıdır');
        $this->assertGreaterThan(50, $xml->count());
    }

    /** Mövzu sınağı və rüb səhifələri yalnız imtahan olanda sitemap-a düşür. */
    public function test_topic_trial_pages_appear_only_when_exams_exist(): void
    {
        $this->assertStringNotContainsString('movzu-sinagi', $this->get('/sitemap.xml')->getContent());

        $category = Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail();
        $topic = Topic::factory()->quarter(2)->create();

        Exam::factory()->published()->create([
            'category_id' => $category->id,
            'subject_id' => $topic->subject_id,
            'kind' => Exam::KIND_TOPIC_TRIAL,
            'quarter' => 2,
        ]);

        Cache::flush();
        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString(url('abituriyent/1-ci-qrup/movzu-sinagi'), $xml);
        $this->assertStringContainsString(url('abituriyent/1-ci-qrup/movzu-sinagi/2-ci-rub'), $xml);
        $this->assertStringContainsString(url('ru/abiturient/1-ya-gruppa/movzu-sinagi/2-ci-rub'), $xml);
    }

    /** Dərc olunmuş imtahanın ictimai səhifəsi sitemap-a düşür, qaralama düşmür. */
    public function test_published_exams_appear_in_the_sitemap(): void
    {
        $category = Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail();

        $published = Exam::factory()->published()->create([
            'category_id' => $category->id,
            'title' => 'Dərc olunmuş sınaq',
        ]);

        $draft = Exam::factory()->create([
            'category_id' => $category->id,
            'title' => 'Qaralama sınaq',
            'is_published' => false,
        ]);

        Cache::flush();
        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString(url('imtahan/'.$published->slug), $xml);
        $this->assertStringContainsString(url('ru/imtahan/'.$published->slug), $xml);
        $this->assertStringNotContainsString($draft->slug, $xml);
    }

    /** İmtahan səhifəsi canonical və hər iki dilin hreflang-ını verir. */
    public function test_the_exam_page_has_canonical_and_hreflang(): void
    {
        $exam = Exam::factory()->published()->create(['title' => 'Kanonik sınaq']);

        $response = $this->get($exam->publicUrl())->assertOk();

        $response->assertSee('rel="canonical" href="'.url('imtahan/'.$exam->slug).'"', false);
        $response->assertSee('hreflang="ru" href="'.url('ru/imtahan/'.$exam->slug).'"', false);
    }

    public function test_the_sitemap_is_cached_until_a_category_changes(): void
    {
        $this->get('/sitemap.xml');

        Category::where('path', 'magistratura')->update(['is_active' => false]);

        // Keş hələ köhnə siyahını qaytarır
        $this->assertStringContainsString(url('magistratura'), $this->get('/sitemap.xml')->getContent());

        \App\Http\Controllers\SitemapController::forget();

        $this->assertStringNotContainsString(url('magistratura').'<', $this->get('/sitemap.xml')->getContent());
    }

    /** İndeksləşmə açıq olanda robots.txt sitemap-ı göstərir (bax: SearchIndexingTest) */
    public function test_robots_points_to_the_sitemap(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production', 'seo.indexing' => true]);

        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
    }

    // ---- Admin ----

    public function test_an_admin_can_edit_the_russian_texts_and_path(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::where('path', 'abituriyent/kollec')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'parent_id' => $category->parent_id,
            'slug' => $category->slug,
            'name' => $category->name,
            'is_active' => true,
            'has_exams' => true,
            'order' => $category->order,
            'ru_path' => 'abiturient/kolledzh-2026',
            'translations' => [
                'name' => 'Колледж',
                'seo_title' => 'Приём в колледж',
                'h1' => 'Приём в колледж',
            ],
        ])->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertSame('abiturient/kolledzh-2026', $category->ru_path);
        $this->assertSame('Колледж', $category->localized('name', 'ru'));
        $this->assertSame('Приём в колледж', $category->localized('seo_title', 'ru'));

        $this->get('/ru/abiturient/kolledzh-2026')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('category.name', 'Колледж')
                ->where('meta.title', 'Приём в колледж'));
    }

    /** Eyni rusca ünvan iki kateqoriyada ola bilməz. */
    public function test_a_duplicate_russian_path_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::where('path', 'abituriyent/kollec')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'parent_id' => $category->parent_id,
            'slug' => $category->slug,
            'name' => $category->name,
            'ru_path' => 'abiturient',
        ])->assertSessionHasErrors('ru_path');
    }

    /** Valideynin rusca ünvanı dəyişəndə alt ağac da yenilənir. */
    public function test_changing_the_parent_russian_path_repaths_the_subtree(): void
    {
        $admin = User::factory()->admin()->create();
        $group = Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.categories.update', $group), [
            'parent_id' => $group->parent_id,
            'slug' => $group->slug,
            'name' => $group->name,
            'ru_path' => 'abiturient/gruppa-1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'abiturient/gruppa-1/rk',
            Category::where('path', 'abituriyent/1-ci-qrup/rk')->value('ru_path')
        );
    }
}
