<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Şagird axını: qrup → mövzu sınağı → rüb.
 *
 * Rüblər üçün ayrıca kateqoriya sətri yaradılmır — CategoryController yolun sonundakı
 * `/movzu-sinagi` və `/movzu-sinagi/{n}-ci-rub` seqmentlərini özü emal edir.
 */
class TopicTrialFlowTest extends TestCase
{
    use RefreshDatabase;

    private Category $group;

    protected function setUp(): void
    {
        parent::setUp();

        $parent = Category::create(['name' => 'Abituriyent', 'slug' => 'abituriyent', 'path' => 'abituriyent']);

        $this->group = Category::create([
            'name' => 'I qrup',
            'slug' => '1-ci-qrup',
            'path' => 'abituriyent/1-ci-qrup',
            'parent_id' => $parent->id,
            'group_id' => Group::factory()->create()->id,
        ]);
    }

    private function trial(int $quarter, bool $published = true): Exam
    {
        return Exam::factory()->create([
            'category_id' => $this->group->id,
            'kind' => Exam::KIND_TOPIC_TRIAL,
            'quarter' => $quarter,
            'is_published' => $published,
            'is_active' => true,
            'title' => "{$quarter}-ci rüb sınağı",
        ]);
    }

    public function test_the_group_page_links_to_the_topic_trial_when_one_exists(): void
    {
        $this->trial(2);

        $this->get('/abituriyent/1-ci-qrup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hasTopicTrials', true)
                ->where('topicTrialUrl', url('abituriyent/1-ci-qrup/movzu-sinagi')));
    }

    public function test_the_link_is_hidden_without_published_trials(): void
    {
        $this->trial(2, published: false);

        $this->get('/abituriyent/1-ci-qrup')
            ->assertInertia(fn ($page) => $page->where('hasTopicTrials', false));
    }

    public function test_the_topic_trial_page_lists_the_available_quarters(): void
    {
        $this->trial(1);
        $this->trial(2);
        $this->trial(2);

        $this->get('/abituriyent/1-ci-qrup/movzu-sinagi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Category/Show')
                ->where('view', 'topic_trial')
                ->where('quarter', null)
                ->has('quarters', 2)
                ->where('quarters.0.quarter', 1)
                ->where('quarters.1.quarter', 2)
                ->where('quarters.1.exams', 2)
                // Rüb seçimi səhifəsində imtahan siyahısı göstərilmir
                ->has('exams', 0));
    }

    public function test_the_quarter_page_lists_only_that_quarters_exams(): void
    {
        $this->trial(1);
        $second = $this->trial(2);

        $this->get('/abituriyent/1-ci-qrup/movzu-sinagi/2-ci-rub')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('view', 'topic_trial')
                ->where('quarter', 2)
                ->has('exams', 1)
                ->where('exams.0.slug', $second->slug));
    }

    /** Ümumi sınaq rüb səhifəsinə düşməməlidir. */
    public function test_a_general_exam_is_not_listed_under_a_quarter(): void
    {
        Exam::factory()->published()->create([
            'category_id' => $this->group->id,
            'kind' => Exam::KIND_GENERAL,
        ]);

        $this->get('/abituriyent/1-ci-qrup/movzu-sinagi/2-ci-rub')
            ->assertInertia(fn ($page) => $page->has('exams', 0));
    }

    /** Rusca ünvanı olmayan düyün Azərbaycan yolu ilə açılır (tərcümə gözləmir). */
    public function test_the_russian_url_works(): void
    {
        $this->trial(2);

        $this->get('/ru/abituriyent/1-ci-qrup/movzu-sinagi/2-ci-rub')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('quarter', 2));
    }

    /** Rusca ünvan varsa, mövzu sınağı seqmentləri də onun ardınca gəlir. */
    public function test_the_translated_url_is_used_when_it_exists(): void
    {
        $this->trial(2);
        $this->group->update(['ru_path' => 'abiturient/1-ya-gruppa']);

        $this->get('/ru/abiturient/1-ya-gruppa/movzu-sinagi/2-ci-rub')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('quarter', 2)
                ->where('seo.canonical', url('ru/abiturient/1-ya-gruppa/movzu-sinagi/2-ci-rub')));

        // Köhnə (Azərbaycan) ünvan tək kanonik ünvana yönləndirilir
        $this->get('/ru/abituriyent/1-ci-qrup/movzu-sinagi/2-ci-rub')
            ->assertRedirect(url('ru/abiturient/1-ya-gruppa/movzu-sinagi/2-ci-rub'))
            ->assertStatus(301);
    }

    /** Rüb seqmenti yalnız "movzu-sinagi"dən sonra gələ bilər. */
    public function test_a_quarter_without_the_topic_trial_segment_is_not_found(): void
    {
        $this->trial(2);

        $this->get('/abituriyent/1-ci-qrup/2-ci-rub')->assertNotFound();
    }

    public function test_an_unknown_category_with_a_trial_segment_is_not_found(): void
    {
        $this->get('/yoxdur/movzu-sinagi')->assertNotFound();
        $this->get('/yoxdur/movzu-sinagi/2-ci-rub')->assertNotFound();
    }

    /** Bu səhifələr üçün canonical düzgün qurulmalıdır (sitemap Mərhələ 6-da). */
    public function test_the_canonical_points_to_the_quarter_page(): void
    {
        $this->trial(2);

        $this->get('/abituriyent/1-ci-qrup/movzu-sinagi/2-ci-rub')
            ->assertInertia(fn ($page) => $page
                ->where('seo.canonical', url('abituriyent/1-ci-qrup/movzu-sinagi/2-ci-rub')));
    }
}
