<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Tag;
use App\Support\GradeMention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sinif etiketi ilə kateqoriya arasındakı iş bölgüsü.
 *
 * QAYDA: sinif etiketi yalnız kateqoriya adı sinfi GÖSTƏRMƏYƏNDƏ işlədilir. Əks halda
 * kataloqda "9-cu sinif buraxılış" bölməsi ilə "9-cu sinif" etiketi yan-yana düşür və
 * fərqi anlaşılmır — ona görə belə səhifədə "Sinif" filtri ümumiyyətlə göstərilmir.
 */
class GradeTagVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Category $school;

    private Category $ninth;

    private Group $group;

    private Tag $fourth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create();

        // Adı neytraldır: səviyyəni yalnız etiket bildirir
        $this->school = Category::create([
            'name' => 'Orta məktəb', 'slug' => 'mekteb', 'path' => 'mekteb', 'has_exams' => true,
        ]);

        // Adı sinfi özü bildirir
        $this->ninth = Category::create([
            'name' => '9-cu sinif buraxılış',
            'slug' => '9-cu-sinif-buraxilis',
            'path' => 'mekteb/9-cu-sinif-buraxilis',
            'parent_id' => $this->school->id,
            'has_exams' => true,
        ]);

        $this->fourth = Tag::create([
            'slug' => '4-cu-sinif', 'name' => '4-cü sinif', 'kind' => Tag::KIND_GRADE, 'order' => 4,
        ]);
    }

    private function exam(Category $category, string $slug, array $tags = []): Exam
    {
        $exam = Exam::factory()->published()->create([
            'slug' => $slug,
            'title' => $slug,
            'category_id' => $category->id,
            'group_id' => $this->group->id,
        ]);

        $exam->tags()->sync($tags);

        return $exam;
    }

    /** @return array<string, mixed> */
    private function props(string $path, array $query = []): array
    {
        $props = [];

        $this->withoutExceptionHandling()
            ->get($path.($query ? '?'.http_build_query($query) : ''))
            ->assertOk()
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    /* ------------------------------------------------------------- qaydanın özü */

    public function test_a_category_name_that_states_a_grade_is_recognised(): void
    {
        $this->assertTrue($this->ninth->mentionsGrade());
        $this->assertFalse($this->school->mentionsGrade());

        // Ad neytraldır, amma valideyn zənciri (yol) sinif bildirir
        $child = Category::create([
            'name' => 'Riyaziyyat',
            'slug' => 'riyaziyyat',
            'path' => 'mekteb/9-cu-sinif-buraxilis/riyaziyyat',
            'parent_id' => $this->ninth->id,
            'has_exams' => true,
        ]);

        $this->assertTrue($child->mentionsGrade());
    }

    /** "1-ci mərhələ", "V qrup", "B kateqoriyası" sinif deyil — yanlış tutulma olmasın. */
    public function test_other_numbered_names_are_not_treated_as_grades(): void
    {
        $this->assertTrue(GradeMention::inText('11 illik'));
        $this->assertTrue(GradeMention::inText('9-cu sinif buraxılış'));
        $this->assertTrue(GradeMention::inText('7 класс'));

        $this->assertFalse(GradeMention::inText('I mərhələ'));
        $this->assertFalse(GradeMention::inText('Abituriyent 2-ci qrup'));
        $this->assertFalse(GradeMention::inText('B kateqoriyası'));
        $this->assertFalse(GradeMention::inText('Tam sınaq'));
    }

    /* ---------------------------------------------------------- kateqoriya səhifəsi */

    /** Adında sinif olan bölmənin səhifəsində "Sinif" filtri görünmür. */
    public function test_the_grade_filter_is_hidden_on_a_category_named_after_a_grade(): void
    {
        $this->exam($this->ninth, 'buraxilis', [$this->fourth->id]);

        $tags = $this->props('/mekteb/9-cu-sinif-buraxilis')['filterOptions']['tags'];

        $this->assertSame([], $tags);
    }

    /** Adı neytral olan bölmədə isə görünür və sayğacı var. */
    public function test_the_grade_filter_is_shown_on_a_neutral_category(): void
    {
        $this->exam($this->school, 'movzu-testi', [$this->fourth->id]);

        $tags = $this->props('/mekteb')['filterOptions']['tags'];

        $this->assertSame(
            [['value' => $this->fourth->id, 'name' => '4-cü sinif', 'kind' => 'grade', 'count' => 1]],
            $tags,
        );
    }

    /** Göründüyü yerdə düzgün süzür. */
    public function test_the_grade_filter_narrows_the_list(): void
    {
        $this->exam($this->school, 'dorduncu', [$this->fourth->id]);
        $this->exam($this->school, 'etiketsiz');

        $exams = $this->props('/mekteb', ['etiket' => $this->fourth->id])['exams'];

        $this->assertSame(['dorduncu'], collect($exams)->pluck('slug')->all());
    }

    /* ----------------------------------------------------------- ümumi kataloq */

    /** `/imtahanlar`-da sinif bildirən kateqoriya seçiləndə də filtr gizlənir. */
    public function test_the_catalog_hides_the_grade_filter_for_a_selected_grade_category(): void
    {
        $this->exam($this->ninth, 'buraxilis', [$this->fourth->id]);
        $this->exam($this->school, 'movzu-testi', [$this->fourth->id]);

        $this->assertNotSame([], $this->props('/imtahanlar')['filterOptions']['tags']);

        $tags = $this->props('/imtahanlar', ['kateqoriya' => $this->ninth->id])['filterOptions']['tags'];

        $this->assertSame([], $tags);
    }

    /* ----------------------------------------------------------------- admin */

    /** Admin forması kateqoriyanın sinif bildirib-bildirmədiyini bilir (xəbərdarlıq üçün). */
    public function test_the_admin_form_knows_which_categories_state_a_grade(): void
    {
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        $categories = [];

        $this->actingAs($admin)
            ->get(route('admin.exams.create'))
            ->assertOk()
            ->assertInertia(function ($page) use (&$categories) {
                $categories = collect($page->toArray()['props']['categories'])->keyBy('id');
            });

        $this->assertTrue($categories[$this->ninth->id]['mentions_grade']);
        $this->assertFalse($categories[$this->school->id]['mentions_grade']);
    }
}
