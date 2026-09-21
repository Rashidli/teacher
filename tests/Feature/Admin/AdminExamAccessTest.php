<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExamAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create(['phone' => '+994551234567']);
        $this->exam = Exam::factory()->published()->paid(20.00)->create();
    }

    private function grant(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->post(
            route('admin.exams.access.store', $this->exam),
            array_merge(['student' => $this->student->email, 'note' => 'Köçürmə, qəbz 12345'], $overrides)
        );
    }

    public function test_admin_can_open_the_access_page(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.exams.access.index', $this->exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Exams/Access'));
    }

    public function test_admin_can_grant_access_by_email(): void
    {
        $this->grant()->assertSessionHasNoErrors();

        $access = ExamAccess::firstOrFail();

        $this->assertSame($this->student->id, $access->user_id);
        $this->assertSame(ExamAccess::SOURCE_MANUAL, $access->source);
        $this->assertSame($this->admin->id, $access->granted_by);
        $this->assertSame('Köçürmə, qəbz 12345', $access->note);
        $this->assertTrue($access->isActive());
    }

    /** Telefon istənilən formatda yazıla bilər. */
    public function test_admin_can_grant_access_by_phone(): void
    {
        $this->grant(['student' => '055 123 45 67'])->assertSessionHasNoErrors();

        $this->assertSame($this->student->id, ExamAccess::firstOrFail()->user_id);
    }

    public function test_an_unknown_student_is_rejected(): void
    {
        $this->grant(['student' => 'yoxdur@example.com'])->assertSessionHasErrors('student');

        $this->assertSame(0, ExamAccess::count());
    }

    /** Email daxil ediləndə telefonsuz hesablar təsadüfən tapılmamalıdır. */
    public function test_a_non_student_account_is_rejected(): void
    {
        $this->grant(['student' => $this->admin->email])->assertSessionHasErrors('student');

        $this->assertSame(0, ExamAccess::count());
    }

    public function test_granting_twice_updates_the_same_row(): void
    {
        $this->grant();
        $this->grant(['note' => 'İkinci qeyd']);

        $this->assertSame(1, ExamAccess::count());
        $this->assertSame('İkinci qeyd', ExamAccess::firstOrFail()->note);
    }

    public function test_access_with_a_limit_and_expiry_is_stored(): void
    {
        $this->grant([
            'expires_at' => now()->addDays(30)->format('Y-m-d\TH:i'),
            'attempts_allowed' => 2,
        ])->assertSessionHasNoErrors();

        $access = ExamAccess::firstOrFail();

        $this->assertSame(2, $access->attempts_allowed);
        $this->assertTrue($access->expires_at->isFuture());
    }

    public function test_an_expiry_in_the_past_is_rejected(): void
    {
        $this->grant(['expires_at' => now()->subDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('expires_at');
    }

    public function test_revoking_closes_the_exam_for_the_student(): void
    {
        $this->grant();
        $access = ExamAccess::firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.exams.access.destroy', [$this->exam, $access]))
            ->assertSessionHasNoErrors();

        $access->refresh();
        $this->assertNotNull($access->revoked_at);
        $this->assertFalse($access->isActive());
        // Qeyd və audit izi silinmir
        $this->assertSame('Köçürmə, qəbz 12345', $access->note);
        $this->assertSame($this->admin->id, $access->granted_by);

        $this->actingAs($this->student, 'student')->post(route('student.exams.start', $this->exam));
        $this->assertSame(0, ExamAttempt::count());
    }

    /** Ləğv edilmiş giriş yenidən veriləndə eyni sətir bərpa olunur. */
    public function test_granting_again_after_a_revoke_reactivates_the_row(): void
    {
        $this->grant();
        $access = ExamAccess::firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.exams.access.destroy', [$this->exam, $access]));

        $this->grant(['note' => 'Yenidən açıldı']);

        $this->assertSame(1, ExamAccess::count());
        $this->assertTrue(ExamAccess::firstOrFail()->isActive());
    }

    /** Vaxtı keçmiş giriş aktiv sayılmır və şagird imtahanı başlada bilmir. */
    public function test_an_expired_access_does_not_allow_starting(): void
    {
        ExamAccess::factory()->expired()->create([
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
        ]);

        $this->actingAs($this->student, 'student')->post(route('student.exams.start', $this->exam));

        $this->assertSame(0, ExamAttempt::count());
    }

    public function test_a_refund_revokes_the_access(): void
    {
        $payment = Payment::factory()->forExam($this->exam)->paid()->create([
            'user_id' => $this->student->id,
        ]);

        $access = ExamAccess::factory()->create([
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'source' => ExamAccess::SOURCE_PAYMENT,
            'payment_id' => $payment->id,
        ]);

        app(PaymentProcessor::class)->refund($payment);

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->refresh()->status);
        $this->assertNotNull($access->refresh()->revoked_at);
        $this->assertFalse($access->isActive());
    }

    public function test_an_access_from_another_exam_can_not_be_revoked_here(): void
    {
        $otherExam = Exam::factory()->create();
        $access = ExamAccess::factory()->create([
            'user_id' => $this->student->id,
            'exam_id' => $otherExam->id,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.exams.access.destroy', [$this->exam, $access]))
            ->assertNotFound();

        $this->assertNull($access->refresh()->revoked_at);
    }

    public function test_students_can_not_manage_access(): void
    {
        $this->actingAs($this->student, 'student')
            ->get(route('admin.exams.access.index', $this->exam))
            ->assertRedirect(route('admin.login'));
    }
}
