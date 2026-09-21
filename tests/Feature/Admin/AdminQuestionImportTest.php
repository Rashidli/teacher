<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminQuestionImportTest extends TestCase
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
            'options_per_question' => 4,
        ]);
    }

    /** Başlıq sətri + verilən sətirlərdən UTF-8 CSV faylı qurur. */
    private function csv(array $rows, array $headings = ['sual', 'tip', 'variant_a', 'variant_b', 'variant_c', 'variant_d', 'duzgun', 'izah']): UploadedFile
    {
        $lines = [implode(',', $headings)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
                $row
            ));
        }

        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($path, implode("\n", $lines));

        return new UploadedFile($path, 'suallar.csv', 'text/csv', null, true);
    }

    private function preview(UploadedFile $file)
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.import.preview', $this->exam), ['file' => $file]);
    }

    public function test_admin_can_open_the_import_page(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.exams.questions.import', $this->exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Questions/Import')->where('rows', null));
    }

    public function test_admin_can_download_the_template(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.exams.questions.import.template', $this->exam));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_preview_reports_valid_rows_without_writing_anything(): void
    {
        $file = $this->csv([
            ['2+2 neçədir?', 'test', '3', '4', '5', '6', 'B', 'Toplama'],
            ['Kəsri yazın', 'qisa', '', '', '', '', '0,5', ''],
            ['Həlli izah edin', 'aciq', '', '', '', '', '', ''],
        ]);

        $this->preview($file)->assertInertia(fn ($page) => $page
            ->component('Admin/Questions/Import')
            ->has('rows', 3)
            ->where('rows.0.errors', [])
            ->where('rows.1.accepted_answers', ['0,5'])
            ->where('rows.2.type', Question::TYPE_OPEN_WRITTEN));

        // Önizləmə bazanı dəyişmir
        $this->assertSame(0, Question::count());
    }

    public function test_preview_flags_broken_rows(): void
    {
        $file = $this->csv([
            ['', 'test', '3', '4', '5', '6', 'B', ''],                    // sual boş
            ['Sual', 'bilinmir', '3', '4', '5', '6', 'A', ''],            // tip tanınmır
            ['Sual', 'test', '3', '', '5', '6', 'A', ''],                 // variant boş
            ['Sual', 'test', '3', '4', '5', '6', 'E', ''],                // düzgün cavab siyahıda yox
            ['Sual', 'qisa', '', '', '', '', '', ''],                     // qısa cavab boş
        ]);

        $this->preview($file)->assertInertia(function ($page) {
            $rows = $page->toArray()['props']['rows'];

            $this->assertCount(5, $rows);
            foreach ($rows as $row) {
                $this->assertNotEmpty($row['errors'], 'Sətir '.$row['number'].' xəta verməliydi');
            }
        });
    }

    public function test_import_writes_the_questions_after_confirmation(): void
    {
        $file = $this->csv([
            ['2+2 neçədir?', 'test', '3', '4', '5', '6', 'B', 'Toplama'],
            ['Kəsri yazın', 'qisa', '', '', '', '', '0,5|yarım', ''],
        ]);

        $token = $this->preview($file)->viewData('page')['props']['token'] ?? null;
        $this->assertNotNull($token);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.import.store', $this->exam), ['token' => $token])
            ->assertRedirect(route('admin.exams.show', $this->exam));

        $this->assertSame(2, Question::count());

        $first = $this->exam->questions()->wherePivot('order', 1)->firstOrFail();
        $this->assertSame(Question::TYPE_MULTIPLE_CHOICE, $first->type);
        $this->assertCount(4, $first->options);
        $this->assertSame('B', $first->options->firstWhere('is_correct', true)->option_letter);

        $second = $this->exam->questions()->wherePivot('order', 2)->firstOrFail();
        $this->assertSame(['0,5', 'yarım'], $second->accepted_answers);
    }

    /** Mövcud suallar varsa, import onların ardınca sıralanmalıdır. */
    public function test_imported_questions_continue_the_existing_order(): void
    {
        $existing = Question::create([
            'subject_id' => $this->exam->subject_id,
            'question_text' => 'Əvvəlki sual',
            'type' => Question::TYPE_OPEN_WRITTEN,
        ]);
        $this->exam->questions()->attach($existing->id, ['order' => 1]);

        $file = $this->csv([['Yeni sual', 'aciq', '', '', '', '', '', '']]);
        $token = $this->preview($file)->viewData('page')['props']['token'];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.import.store', $this->exam), ['token' => $token]);

        $new = Question::where('question_text', 'Yeni sual')->firstOrFail();
        $this->assertSame(2, (int) $this->exam->questions()->where('questions.id', $new->id)->firstOrFail()->pivot->order);
    }

    /** Bir sətir xətalıdırsa heç nə yazılmamalıdır. */
    public function test_a_single_broken_row_stops_the_whole_import(): void
    {
        $file = $this->csv([
            ['Yaxşı sual', 'test', '3', '4', '5', '6', 'B', ''],
            ['Pis sual', 'test', '3', '4', '5', '6', 'E', ''],
        ]);

        $token = $this->preview($file)->viewData('page')['props']['token'];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.import.store', $this->exam), ['token' => $token])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Question::count());
    }

    public function test_a_fifth_option_is_rejected_when_the_exam_uses_four(): void
    {
        $file = $this->csv(
            [['Sual', 'test', '3', '4', '5', '6', '7', 'B', '']],
            ['sual', 'tip', 'variant_a', 'variant_b', 'variant_c', 'variant_d', 'variant_e', 'duzgun', 'izah']
        );

        $this->preview($file)->assertInertia(function ($page) {
            $errors = $page->toArray()['props']['rows'][0]['errors'];

            $this->assertNotEmpty($errors);
            $this->assertStringContainsString('variant', mb_strtolower(implode(' ', $errors)));
        });

        $this->assertSame(0, Question::count());
    }

    public function test_an_unknown_token_does_not_import_anything(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.exams.questions.import.store', $this->exam), ['token' => 'yoxdur.xlsx'])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Question::count());
    }

    public function test_students_can_not_import_questions(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'student')
            ->get(route('admin.exams.questions.import', $this->exam))
            ->assertRedirect(route('admin.login'));
    }
}
