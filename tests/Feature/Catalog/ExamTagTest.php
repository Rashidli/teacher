<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\Subject;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İmtahan etiketləri (sinif səviyyəsi və sərbəst etiketlər).
 *
 * Etiket kateqoriya ağacına ORTOQONALDIR: eyni "9-cu sinif" etiketi həm buraxılış, həm
 * olimpiada imtahanında ola bilər. Kataloqda ayrıca filtr bölməsidir.
 */
class ExamTagTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Group $group;

    private Tag $ninth;

    private Tag $eleventh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create();

        $this->category = Category::create([
            'name' => 'Orta məktəb', 'slug' => 'mekteb', 'path' => 'mekteb',
            'has_exams' => true, 'color' => '#2440A0',
        ]);

        $this->ninth = Tag::create(['slug' => '9-cu-sinif', 'name' => '9-cu sinif', 'kind' => Tag::KIND_GRADE, 'order' => 9]);
        $this->eleventh = Tag::create(['slug' => '11-ci-sinif', 'name' => '11-ci sinif', 'kind' => Tag::KIND_GRADE, 'order' => 11]);
    }

    private function exam(string $slug, array $tags = [], array $attributes = []): Exam
    {
        $exam = Exam::factory()->published()->create(array_merge([
            'slug' => $slug,
            'title' => $slug,
            'category_id' => $this->category->id,
            'group_id' => $this->group->id,
        ], $attributes));

        $exam->tags()->sync($tags);

        return $exam;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $props = [];

        $this->withoutExceptionHandling()
            ->get('/imtahanlar'.($query ? '?'.http_build_query($query) : ''))
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    /* ------------------------------------------------------------- kataloq */

    /** Etiket filtri sayğaclarla gəlir; sinif etiketləri öz sırası ilə (əlifba ilə yox). */
    public function test_the_tag_filter_carries_counts_in_grade_order(): void
    {
        $this->exam('a', [$this->ninth->id]);
        $this->exam('b', [$this->eleventh->id]);
        $this->exam('c', [$this->eleventh->id]);

        $tags = $this->props()['filterOptions']['tags'];

        $this->assertSame(
            [
                ['value' => $this->ninth->id, 'name' => '9-cu sinif', 'kind' => 'grade', 'count' => 1],
                ['value' => $this->eleventh->id, 'name' => '11-ci sinif', 'kind' => 'grade', 'count' => 2],
            ],
            $tags,
        );
    }

    public function test_the_tag_filter_narrows_the_list(): void
    {
        $this->exam('doqquzuncu', [$this->ninth->id]);
        $this->exam('on-birinci', [$this->eleventh->id]);
        $this->exam('etiketsiz');

        $props = $this->props(['etiket' => $this->ninth->id]);

        $this->assertSame('list', $props['mode']);
        $this->assertSame(['doqquzuncu'], collect($props['exams'])->pluck('slug')->all());
        $this->assertSame($this->ninth->id, $props['filters']['etiket']);
    }

    /** Deaktiv etiket filtr siyahısında görünmür. */
    public function test_an_inactive_tag_is_hidden_from_the_filter(): void
    {
        $this->exam('a', [$this->ninth->id]);
        $this->ninth->update(['is_active' => false]);

        $this->assertSame([], $this->props()['filterOptions']['tags']);
    }

    /** Kart sinif etiketini və "Ətraflı" üçün bölmələri daşıyır. */
    public function test_a_card_carries_its_tags_and_sections(): void
    {
        $subject = Subject::create(['name' => 'Riyaziyyat', 'slug' => 'riyaziyyat', 'category' => 'technical']);
        $exam = $this->exam('a', [$this->ninth->id], [
            'subject_id' => $subject->id,
            'description' => 'Qısa izah.',
        ]);

        // Exam factory-si bölməni özü qura bilər
        ExamSection::updateOrCreate(
            ['exam_id' => $exam->id, 'subject_id' => $subject->id],
            ['question_count' => 12, 'order' => 1],
        );

        $card = collect($this->props()['groups'][0]['exams'])->firstWhere('slug', 'a');

        $this->assertSame(
            [['id' => $this->ninth->id, 'name' => '9-cu sinif', 'kind' => 'grade']],
            $card['tags'],
        );
        $this->assertSame([['subject' => 'Riyaziyyat', 'question_count' => 12]], $card['sections']);
        $this->assertSame('Qısa izah.', $card['description']);
    }

    /** Kateqoriya səhifəsində də etiket filtri işləyir. */
    public function test_the_category_page_filters_by_tag(): void
    {
        $this->exam('doqquzuncu', [$this->ninth->id]);
        $this->exam('on-birinci', [$this->eleventh->id]);

        $slugs = [];

        $this->get('/mekteb?etiket='.$this->ninth->id)
            ->assertOk()
            ->assertInertia(function ($page) use (&$slugs) {
                $slugs = collect($page->toArray()['props']['exams'])->pluck('slug')->all();
            });

        $this->assertSame(['doqquzuncu'], $slugs);
    }

    /* --------------------------------------------------------------- admin */

    public function test_an_admin_can_create_and_deactivate_a_tag(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.tags.store'), ['name' => 'Olimpiada', 'kind' => Tag::KIND_OTHER, 'order' => 1])
            ->assertSessionHasNoErrors();

        $tag = Tag::where('name', 'Olimpiada')->firstOrFail();
        $this->assertSame('olimpiada', $tag->slug);

        $this->actingAs($admin)
            ->put(route('admin.tags.update', $tag), [
                'name' => 'Olimpiada', 'kind' => Tag::KIND_OTHER, 'order' => 1, 'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($tag->refresh()->is_active);
    }

    /** İmtahana bağlı etiket silinmir — əlaqə səssizcə itməsin. */
    public function test_a_tag_in_use_can_not_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->exam('a', [$this->ninth->id]);

        $this->actingAs($admin)
            ->delete(route('admin.tags.destroy', $this->ninth))
            ->assertSessionHas('error');

        $this->assertModelExists($this->ninth);
    }

    public function test_an_admin_can_attach_tags_to_an_exam(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $subject = Subject::create(['name' => 'Riyaziyyat', 'slug' => 'riyaziyyat', 'category' => 'technical']);

        $this->actingAs($admin)->post(route('admin.exams.store'), [
            'subject_id' => $subject->id,
            'group_id' => $this->group->id,
            'category_id' => $this->category->id,
            'title' => 'Etiketli imtahan',
            'duration_minutes' => 60,
            'sector' => 'az',
            'is_free' => true,
            'tags' => [$this->ninth->id],
        ])->assertSessionHasNoErrors();

        $exam = Exam::where('title', 'Etiketli imtahan')->firstOrFail();

        $this->assertSame([$this->ninth->id], $exam->tags()->pluck('tags.id')->all());
    }
}
