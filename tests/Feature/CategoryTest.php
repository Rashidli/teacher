<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Subject;
use App\Models\SubjectGroupScore;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\SubjectGroupScoreSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedTree(): void
    {
        $this->seed(SubjectSeeder::class);
        $this->seed(GroupSeeder::class);
        $this->seed(SubjectGroupScoreSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    public function test_the_seeder_builds_the_tree(): void
    {
        $this->seedTree();

        $roots = Category::roots()->orderBy('order')->pluck('path')->all();

        $this->assertSame(
            ['mekteb', 'abituriyent', 'magistratura', 'dovlet-qullugu', 'muellimler', 'suruculuk-imtahani', 'diger'],
            $roots
        );

        // İç-içə yollar valideyn zəncirindən qurulur
        $this->assertNotNull(Category::where('path', 'abituriyent/1-ci-qrup/rk')->first());
        $this->assertNotNull(Category::where('path', 'dovlet-qullugu/tam-sinaq/aa')->first());
    }

    /** Mövcud ünvanlar qorunur: MİQ ağacda "Müəllimlər"in altındadır, URL-i isə /miq. */
    public function test_existing_slugs_are_preserved(): void
    {
        $this->seedTree();

        $miq = Category::where('path', 'miq')->firstOrFail();

        $this->assertSame('Müəllimlər', $miq->parent->name);
        $this->assertSame('miq', $miq->path);

        foreach (['mekteb', 'abituriyent', 'magistratura', 'dovlet-qullugu', 'suruculuk-imtahani'] as $path) {
            $this->assertNotNull(Category::where('path', $path)->first(), $path.' tapılmadı');
        }
    }

    /** Qrup düyünləri mövcud `groups` sətirlərinə bağlanır, ağacda təkrarlanmır. */
    public function test_group_nodes_link_to_the_scoring_groups(): void
    {
        $this->seedTree();

        $this->assertSame('I', Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail()->group->code);
        $this->assertSame('I-RK', Category::where('path', 'abituriyent/1-ci-qrup/rk')->firstOrFail()->group->code);
        $this->assertSame('I-MERHELE', Category::where('path', 'abituriyent/1-ci-merhele')->firstOrFail()->group->code);

        // Qrupu olmayan kateqoriyalar
        $this->assertNull(Category::where('path', 'dovlet-qullugu')->firstOrFail()->group);
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seedTree();
        $count = Category::count();

        $this->seed(CategorySeeder::class);

        $this->assertSame($count, Category::count());
    }

    /** Seeder təkrar işləyəndə adminin deaktiv etdiyi kateqoriya geri açılmamalıdır. */
    public function test_reseeding_keeps_manual_deactivation(): void
    {
        $this->seedTree();

        Category::where('path', 'magistratura')->update(['is_active' => false]);

        $this->seed(CategorySeeder::class);

        $this->assertFalse(Category::where('path', 'magistratura')->firstOrFail()->is_active);
    }

    public function test_the_category_page_renders(): void
    {
        $this->seedTree();

        $this->get('/abituriyent')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Category/Show')
                ->where('category.name', 'Abituriyent')
                ->has('children', 7));
    }

    public function test_a_nested_category_page_renders_with_a_breadcrumb(): void
    {
        $this->seedTree();

        $this->get('/abituriyent/1-ci-qrup/rk')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('category.name', 'RK altqrupu')
                // Ana səhifə ayrıca göstərilir: zəncir kök kateqoriyadan başlayır
                ->has('breadcrumb', 3)
                ->where('breadcrumb.0.name', 'Abituriyent')
                ->where('breadcrumb.2.name', 'RK altqrupu'));
    }

    public function test_an_inactive_category_is_not_reachable(): void
    {
        $this->seedTree();

        $this->get('/diger')->assertNotFound();
    }

    /** Kateqoriya səhifəsi öz imtahanları ilə yanaşı alt düyünlərin imtahanlarını da göstərir. */
    public function test_the_page_lists_exams_from_the_whole_subtree(): void
    {
        $this->seedTree();

        $group = Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail();
        $subgroup = Category::where('path', 'abituriyent/1-ci-qrup/rk')->firstOrFail();

        Exam::factory()->published()->create(['category_id' => $group->id, 'title' => 'Qrup imtahanı']);
        Exam::factory()->published()->create(['category_id' => $subgroup->id, 'title' => 'Altqrup imtahanı']);
        Exam::factory()->published()->create(['title' => 'Kateqoriyasız']);

        $this->get('/abituriyent/1-ci-qrup')
            ->assertInertia(fn ($page) => $page->has('exams', 2));

        // Altqrup səhifəsində yalnız öz imtahanı
        $this->get('/abituriyent/1-ci-qrup/rk')
            ->assertInertia(fn ($page) => $page->has('exams', 1)
                ->where('exams.0.title', 'Altqrup imtahanı'));
    }

    /** Yayımlanmamış imtahan ictimai səhifədə görünmür. */
    public function test_unpublished_exams_are_hidden(): void
    {
        $this->seedTree();
        $category = Category::where('path', 'abituriyent/2-ci-qrup')->firstOrFail();

        Exam::factory()->create(['category_id' => $category->id, 'is_published' => false]);

        $this->get('/abituriyent/2-ci-qrup')->assertInertia(fn ($page) => $page->has('exams', 0));
    }

    /** Bal pivotda göstərilməyibsə qrupun bal matrisindən götürülür. */
    public function test_the_max_score_falls_back_to_the_group_matrix(): void
    {
        $this->seedTree();

        $category = Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail();
        $subject = Subject::where('slug', 'riyaziyyat')->firstOrFail();

        $category->load('subjects', 'group');

        $this->assertSame(150.0, $category->maxScoreFor($subject));
    }

    /** Qrupu olmayan kateqoriyada bal pivotdan gəlir. */
    public function test_the_max_score_comes_from_the_pivot_when_there_is_no_group(): void
    {
        $this->seedTree();

        $category = Category::where('path', 'dovlet-qullugu')->firstOrFail()->load('subjects', 'group');
        $subject = Subject::where('slug', 'qanunvericilik')->firstOrFail();

        $this->assertSame(40.0, $category->maxScoreFor($subject));
        $this->assertSame(40, $category->subjects->firstWhere('id', $subject->id)->pivot->question_count);
    }

    public function test_the_russian_page_uses_the_translated_name(): void
    {
        $this->seedTree();

        $this->get('/ru/abituriyent')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('category.name', 'Абитуриент'));
    }

    /** Tərcüməsi olmayan düyün rus səhifəsində Azərbaycan adı ilə görünür (boş qalmır). */
    public function test_an_untranslated_node_falls_back_to_azerbaijani(): void
    {
        $this->seedTree();

        $this->get('/ru/abituriyent/2-ci-qrup')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('category.name', 'II qrup'));
    }

    public function test_root_categories_are_shared_with_every_page(): void
    {
        $this->seedTree();

        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('categories', 6) // "Digər" deaktivdir
            ->where('categories.0.path', 'mekteb'));
    }

    // ---- Admin ----

    public function test_an_admin_can_create_a_category(): void
    {
        $this->seedTree();
        $admin = User::factory()->admin()->create();
        $parent = Category::where('path', 'magistratura')->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.categories.store'), [
            'parent_id' => $parent->id,
            'name' => 'Yeni bölmə',
            'slug' => 'yeni-bolme',
            'is_active' => true,
            'has_exams' => true,
            'order' => 5,
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertNotNull(Category::where('path', 'magistratura/yeni-bolme')->first());
    }

    public function test_an_invalid_slug_is_rejected(): void
    {
        $this->seedTree();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.categories.store'), [
            'parent_id' => null,
            'name' => 'Səhv slug',
            'slug' => 'Səhv Slug',
        ])->assertSessionHasErrors('slug');
    }

    /** Valideyn dəyişəndə alt ağacın URL-ləri də yenilənməlidir. */
    public function test_renaming_a_slug_repaths_the_subtree(): void
    {
        $this->seedTree();
        $admin = User::factory()->admin()->create();
        $group = Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail();

        $this->actingAs($admin, 'admin')->put(route('admin.categories.update', $group), [
            'parent_id' => $group->parent_id,
            'group_id' => $group->group_id,
            'name' => $group->name,
            'slug' => 'birinci-qrup',
            'is_active' => true,
            'has_exams' => true,
            'order' => $group->order,
        ])->assertSessionHasNoErrors();

        $this->assertSame('abituriyent/birinci-qrup', $group->refresh()->path);
        $this->assertNotNull(Category::where('path', 'abituriyent/birinci-qrup/rk')->first());
        $this->get('/abituriyent/birinci-qrup/rk')->assertOk();
    }

    public function test_a_category_can_not_be_moved_under_its_own_child(): void
    {
        $this->seedTree();
        $admin = User::factory()->admin()->create();
        $group = Category::where('path', 'abituriyent/1-ci-qrup')->firstOrFail();
        $child = Category::where('path', 'abituriyent/1-ci-qrup/rk')->firstOrFail();

        $this->actingAs($admin, 'admin')->put(route('admin.categories.update', $group), [
            'parent_id' => $child->id,
            'name' => $group->name,
            'slug' => $group->slug,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_a_category_with_children_can_not_be_deleted(): void
    {
        $this->seedTree();
        $admin = User::factory()->admin()->create();
        $category = Category::where('path', 'abituriyent')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertNotNull($category->fresh());
    }

    /** İmtahanın qrupu kateqoriyadan götürülür (iki mənbə ziddiyyət yaratmasın). */
    public function test_the_exam_group_is_taken_from_the_category(): void
    {
        $this->seedTree();
        $admin = User::factory()->admin()->create();
        config(['features.teachers' => false, 'features.exam_owner_id' => $admin->id]);

        $category = Category::where('path', 'abituriyent/4-cu-qrup')->firstOrFail();
        $otherGroup = Group::where('code', 'II')->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.exams.store'), [
            'subject_id' => Subject::where('slug', 'fizika')->value('id'),
            // Səhvən başqa qrup seçilib: kateqoriyanınkı üstün gəlməlidir
            'group_id' => $otherGroup->id,
            'category_id' => $category->id,
            'title' => 'IV qrup sınağı',
            'duration_minutes' => 60,
            'sector' => 'az',
            'is_free' => true,
        ])->assertSessionHasNoErrors();

        $exam = Exam::firstOrFail();

        $this->assertSame($category->id, $exam->category_id);
        $this->assertSame($category->group_id, $exam->group_id);
    }

    public function test_students_can_not_manage_categories(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'student')
            ->get(route('admin.categories.index'))
            ->assertRedirect(route('admin.login'));
    }

    /**
     * Kateqoriya səhifələrində canonical və hreflang olmalıdır.
     * Prop adı "seo" olsaydı, paylaşılan canonical prop-unu üzərinə yazardı.
     */
    public function test_a_category_page_keeps_its_canonical_and_hreflang(): void
    {
        $this->seedTree();

        $this->get('/abituriyent')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.canonical', url('abituriyent'))
                ->where('seo.alternates.ru', url('ru/abituriyent'))
                ->where('meta.title', 'Abituriyent'));
    }
}
