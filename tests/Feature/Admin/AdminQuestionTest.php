<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminQuestionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->exam = Exam::factory()->create([
            'teacher_id' => $this->admin->id,
            'options_per_question' => 5,
        ]);

        config(['features.teachers' => false, 'features.exam_owner_id' => $this->admin->id]);
    }

    private function optionsPayload(int $count = 5, int $correctIndex = 0): array
    {
        return collect(range(0, $count - 1))->map(fn ($index) => [
            'option_letter' => chr(65 + $index),
            'option_text' => 'Variant '.chr(65 + $index),
            'is_correct' => $index === $correctIndex,
        ])->all();
    }

    public function test_admin_can_open_the_create_page(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.exams.questions.create', $this->exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Questions/Create'));
    }

    public function test_admin_can_add_a_multiple_choice_question(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.store', $this->exam), [
                'question_text' => 'Tənliyi həll edin: $x^2 = 4$',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'options' => $this->optionsPayload(5, 2),
                'explanation' => 'Kvadrat tənlik',
            ]);

        $response->assertRedirect(route('admin.exams.show', $this->exam));

        $question = Question::firstOrFail();

        $this->assertSame(Question::TYPE_MULTIPLE_CHOICE, $question->type);
        $this->assertSame(1, $question->order);
        $this->assertCount(5, $question->options);
        $this->assertSame('C', $question->options->firstWhere('is_correct', true)->option_letter);
        // Variantlı sualda qısa cavab sahəsi saxlanılmır
        $this->assertNull($question->accepted_answers);
    }

    public function test_option_count_must_match_the_exam_setting(): void
    {
        $this->exam->update(['options_per_question' => 4]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.store', $this->exam), [
                'question_text' => 'Sual',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'options' => $this->optionsPayload(5),
            ])
            ->assertSessionHasErrors('options');

        $this->assertSame(0, Question::count());
    }

    public function test_exactly_one_option_must_be_correct(): void
    {
        $options = $this->optionsPayload();
        $options[1]['is_correct'] = true;

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.store', $this->exam), [
                'question_text' => 'Sual',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'options' => $options,
            ])
            ->assertSessionHasErrors('options');
    }

    public function test_admin_can_add_an_open_coded_question(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.store', $this->exam), [
                'question_text' => 'Kəsri hesablayın',
                'type' => Question::TYPE_OPEN_CODED,
                'accepted_answers' => ['0,5', '', 'x=2'],
            ])
            ->assertSessionHasNoErrors();

        $question = Question::firstOrFail();

        // Boş sətirlər atılır
        $this->assertSame(['0,5', 'x=2'], $question->accepted_answers);
        $this->assertCount(0, $question->options);
    }

    public function test_an_open_coded_question_needs_at_least_one_answer(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.store', $this->exam), [
                'question_text' => 'Sual',
                'type' => Question::TYPE_OPEN_CODED,
                'accepted_answers' => ['', '  '],
            ])
            ->assertSessionHasErrors('accepted_answers');
    }

    public function test_admin_can_add_an_open_written_question(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.store', $this->exam), [
                'question_text' => 'Həlli izah edin',
                'type' => Question::TYPE_OPEN_WRITTEN,
            ])
            ->assertSessionHasNoErrors();

        $question = Question::firstOrFail();

        $this->assertSame(Question::TYPE_OPEN_WRITTEN, $question->type);
        $this->assertFalse($question->isAutoGraded());
    }

    public function test_admin_can_upload_a_question_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.store', $this->exam), [
                'question_text' => 'Şəkildəki fiquru tapın',
                'type' => Question::TYPE_OPEN_WRITTEN,
                'question_image' => UploadedFile::fake()->image('sekil.png'),
            ])
            ->assertSessionHasNoErrors();

        $path = Question::firstOrFail()->question_image;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_editing_a_question_keeps_the_existing_option_images(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.questions.store', $this->exam), [
            'question_text' => 'Sual',
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => collect($this->optionsPayload())->map(fn ($option, $index) => $index === 0
                ? $option + ['option_image' => UploadedFile::fake()->image('variant.png')]
                : $option)->all(),
        ])->assertSessionHasNoErrors();

        $question = Question::firstOrFail();
        $imagePath = $question->options->firstWhere('option_letter', 'A')->option_image;
        $this->assertNotNull($imagePath);

        // Yalnız mətn dəyişir, şəkil yenidən yüklənmir
        $this->actingAs($this->admin, 'admin')->put(
            route('admin.exams.questions.update', [$this->exam, $question]),
            [
                'question_text' => 'Yenilənmiş sual',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'options' => $this->optionsPayload(),
            ]
        )->assertSessionHasNoErrors();

        $this->assertSame($imagePath, $question->refresh()->options->firstWhere('option_letter', 'A')->option_image);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_changing_the_type_to_open_removes_the_options(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.questions.store', $this->exam), [
            'question_text' => 'Sual',
            'type' => Question::TYPE_MULTIPLE_CHOICE,
            'options' => $this->optionsPayload(),
        ]);

        $question = Question::firstOrFail();

        $this->actingAs($this->admin, 'admin')->put(
            route('admin.exams.questions.update', [$this->exam, $question]),
            [
                'question_text' => 'Sual',
                'type' => Question::TYPE_OPEN_CODED,
                'accepted_answers' => ['7'],
            ]
        )->assertSessionHasNoErrors();

        $this->assertCount(0, $question->refresh()->options);
        $this->assertSame(['7'], $question->accepted_answers);
    }

    public function test_admin_can_delete_a_question_and_the_order_is_closed_up(): void
    {
        $first = $this->createQuestion('Birinci');
        $second = $this->createQuestion('İkinci');
        $third = $this->createQuestion('Üçüncü');

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.exams.questions.destroy', [$this->exam, $second]))
            ->assertRedirect(route('admin.exams.show', $this->exam));

        $this->assertNull(Question::find($second->id));
        $this->assertSame(1, $first->refresh()->order);
        $this->assertSame(2, $third->refresh()->order);
    }

    public function test_admin_can_move_a_question_up_and_down(): void
    {
        $first = $this->createQuestion('Birinci');
        $second = $this->createQuestion('İkinci');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.move', [$this->exam, $second, 'up']));

        $this->assertSame(1, $second->refresh()->order);
        $this->assertSame(2, $first->refresh()->order);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.move', [$this->exam, $second, 'down']));

        $this->assertSame(2, $second->refresh()->order);
        $this->assertSame(1, $first->refresh()->order);
    }

    public function test_moving_the_first_question_up_changes_nothing(): void
    {
        $first = $this->createQuestion('Birinci');
        $second = $this->createQuestion('İkinci');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.move', [$this->exam, $first, 'up']));

        $this->assertSame(1, $first->refresh()->order);
        $this->assertSame(2, $second->refresh()->order);
    }

    /** URL-dəki sual başqa imtahana aiddirsə 404 qaytarılmalıdır. */
    public function test_a_question_from_another_exam_is_not_reachable(): void
    {
        $otherExam = Exam::factory()->create(['teacher_id' => $this->admin->id]);
        $question = $this->createQuestion('Başqa imtahanın sualı', $otherExam);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.exams.questions.edit', [$this->exam, $question]))
            ->assertNotFound();
    }

    public function test_students_can_not_manage_questions(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'student')
            ->get(route('admin.exams.questions.create', $this->exam))
            ->assertRedirect(route('admin.login'));
    }

    private function createQuestion(string $text, ?Exam $exam = null): Question
    {
        $exam ??= $this->exam;

        $this->actingAs($this->admin, 'admin')->post(route('admin.exams.questions.store', $exam), [
            'question_text' => $text,
            'type' => Question::TYPE_OPEN_WRITTEN,
        ])->assertSessionHasNoErrors();

        return Question::where('exam_id', $exam->id)->orderByDesc('id')->firstOrFail();
    }
}
