<?php

namespace Tests\Feature;

use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `SEO_INDEXING` bayrağı: bağlı olanda sayt TAM bağlanır — meta teq, `X-Robots-Tag`
 * başlığı və `robots.txt`. Bayraq `APP_ENV`-dən asılı deyil (müvəqqəti domen üçün),
 * amma produksiyadan kənarda indeksləşmə hər halda bağlıdır.
 */
class SearchIndexingTest extends TestCase
{
    use RefreshDatabase;

    /** İndeksləşmənin açıq olduğu yeganə hal: produksiya + bayraq açıq */
    private function allowIndexing(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production', 'seo.indexing' => true]);
    }

    private function blockIndexing(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production', 'seo.indexing' => false]);
    }

    public function test_the_flag_alone_decides_in_production(): void
    {
        $this->allowIndexing();
        $this->assertTrue(Seo::indexable());

        $this->blockIndexing();
        $this->assertFalse(Seo::indexable());
    }

    /** Produksiyadan kənarda bayraq açıq olsa belə indeksləşmə bağlıdır */
    public function test_outside_production_indexing_stays_closed(): void
    {
        config(['seo.indexing' => true]);

        $this->assertFalse(Seo::indexable());
    }

    // ---- Başlıq və meta teq ----

    public function test_a_blocked_site_sends_the_header_and_the_meta_tag(): void
    {
        $this->blockIndexing();

        $response = $this->get('/');

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_an_open_site_sends_neither(): void
    {
        $this->allowIndexing();

        $response = $this->get('/');

        $this->assertNull($response->headers->get('X-Robots-Tag'));
        $response->assertDontSee('name="robots"', false);
    }

    /** Panel səhifələri də bağlı nüsxədə noindex alır */
    public function test_the_header_covers_every_route(): void
    {
        $this->blockIndexing();

        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/sitemap.xml')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_shared_prop_tells_the_frontend(): void
    {
        $this->blockIndexing();
        $this->get('/')->assertInertia(fn ($page) => $page->where('indexable', false));

        $this->allowIndexing();
        $this->get('/')->assertInertia(fn ($page) => $page->where('indexable', true));
    }

    // ---- robots.txt ----

    public function test_robots_closes_the_whole_site_when_indexing_is_off(): void
    {
        $this->blockIndexing();

        $response = $this->get('/robots.txt')->assertOk();

        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());

        // Bağlı saytda sitemap göstərilmir: robot ünvanları oradan tapmasın
        $response->assertDontSee('Sitemap');
    }

    public function test_robots_opens_the_public_pages_when_indexing_is_on(): void
    {
        $this->allowIndexing();

        $content = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /admin', $content);
        $this->assertStringContainsString('Disallow: /student', $content);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $content);
        $this->assertStringNotContainsString("Disallow: /\n", $content);
    }

    /** Statik fayl qalsaydı, nginx/Apache onu verər və bayraq işləməzdi */
    public function test_no_static_robots_file_shadows_the_route(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }
}
