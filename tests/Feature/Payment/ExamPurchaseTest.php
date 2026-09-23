<?php

namespace Tests\Feature\Payment;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alış axını.
 *
 * TEST REJİMİ (`PAYMENT_DRIVER=fake`): "Al" basılanda bank səhifəsi açılmır — ödəniş
 * dərhal "paid" olur və giriş açılır.
 *
 * Bank cavabının (callback) emalı ayrıca yoxlanılır: real provayder gələndə axın məhz
 * oradan keçəcək, ona görə gözləyən ödəniş `pendingPayment()` ilə birbaşa qurulur.
 */
class ExamPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.driver' => 'fake', 'payments.access_valid_days' => null]);

        $this->student = User::factory()->student()->create();
        $this->exam = Exam::factory()->published()->paid(15.00)->create();
    }

    /**
     * Bank cavabını gözləyən ödəniş. "Al" axını test rejimində ödənişi dərhal bağladığı
     * üçün callback testləri sətiri birbaşa qurur — real provayderdəki vəziyyət budur.
     */
    private function pendingPayment(): Payment
    {
        return app(PaymentProcessor::class)->startExamPurchase(
            $this->student,
            $this->exam,
            new FakePaymentGateway,
        );
    }

    private function sendCallback(Payment $payment, string $status = 'success', array $extra = []): \Illuminate\Testing\TestResponse
    {
        $reference = (string) $payment->id;

        return $this->post(route('payments.callback', 'fake'), array_merge([
            'reference' => $reference,
            'status' => $status,
            'signature' => FakePaymentGateway::signature($reference, $status),
        ], $extra));
    }

    public function test_a_paid_exam_can_not_be_started_without_access(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertRedirect($this->exam->publicUrl());

        $this->assertSame(0, ExamAttempt::count());
    }

    public function test_a_free_exam_can_be_started_without_a_purchase(): void
    {
        $free = Exam::factory()->published()->create(['is_free' => true, 'price' => 0]);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $free));

        $this->assertSame(1, ExamAttempt::count());
    }

    /** Test rejimi: "Al" → ödəniş dərhal təsdiqlənir → giriş açılır → imtahan başladıla bilir. */
    public function test_buying_in_test_mode_pays_immediately_and_opens_the_exam(): void
    {
        $response = $this->actingAs($this->student)
            ->post(route('student.exams.purchase', $this->exam));

        $payment = Payment::firstOrFail();

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        // Ödəniş qeydində provayder görünür və test rejimi işarələnir
        $this->assertSame('fake', $payment->provider);
        $this->assertTrue($payment->payload['test_mode']);
        // Məbləğ alış anındakı qiymətdir
        $this->assertSame('15.00', $payment->amount);
        $this->assertSame('AZN', $payment->currency);
        $this->assertSame($this->exam->getMorphClass(), $payment->purchasable_type);
        $this->assertSame($this->exam->id, $payment->purchasable_id);

        // Bank səhifəsinə yönləndirmə yoxdur: şagird imtahan səhifəsinə qayıdır
        $response->assertRedirect($this->exam->publicUrl());

        $access = ExamAccess::firstOrFail();
        $this->assertSame(ExamAccess::SOURCE_PAYMENT, $access->source);
        $this->assertTrue($access->isActive());

        $this->actingAs($this->student)->post(route('student.exams.start', $this->exam));
        $this->assertSame(1, ExamAttempt::count());
    }

    public function test_a_successful_callback_opens_the_exam(): void
    {
        $payment = $this->pendingPayment();

        $this->sendCallback($payment)->assertRedirect($this->exam->publicUrl());

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $access = ExamAccess::firstOrFail();
        $this->assertSame(ExamAccess::SOURCE_PAYMENT, $access->source);
        $this->assertSame($payment->id, $access->payment_id);
        $this->assertTrue($access->isActive());

        // Giriş açıldı: imtahan başladıla bilər
        $this->actingAs($this->student)->post(route('student.exams.start', $this->exam));
        $this->assertSame(1, ExamAttempt::count());
    }

    /** Eyni callback iki dəfə gəlsə giriş iki dəfə yaradılmamalıdır. */
    public function test_the_callback_is_idempotent(): void
    {
        $payment = $this->pendingPayment();

        $this->sendCallback($payment);
        $paidAt = $payment->refresh()->paid_at;

        $this->sendCallback($payment);

        $this->assertSame(1, ExamAccess::count());
        $this->assertSame(Payment::STATUS_PAID, $payment->refresh()->status);
        $this->assertEquals($paidAt, $payment->paid_at);
    }

    public function test_a_failed_callback_does_not_open_the_exam(): void
    {
        $payment = $this->pendingPayment();

        $this->sendCallback($payment, 'failed');

        $this->assertSame(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertSame(0, ExamAccess::count());
    }

    /** Uğursuz ödəniş sonradan "uğurlu" callback ilə açıla bilməz. */
    public function test_a_failed_payment_can_not_become_paid(): void
    {
        $payment = $this->pendingPayment();

        $this->sendCallback($payment, 'failed');
        $this->sendCallback($payment, 'success');

        $this->assertSame(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertSame(0, ExamAccess::count());
    }

    public function test_a_callback_with_a_wrong_signature_is_rejected(): void
    {
        $payment = $this->pendingPayment();

        $this->post(route('payments.callback', 'fake'), [
            'reference' => (string) $payment->id,
            'status' => 'success',
            'signature' => 'yanlis-imza',
        ])->assertForbidden();

        $this->assertSame(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertSame(0, ExamAccess::count());
    }

    public function test_card_data_is_not_stored_in_the_payload(): void
    {
        $payment = $this->pendingPayment();

        $this->sendCallback($payment, 'success', [
            'pan' => '4169738912345678',
            'cvv' => '123',
            'exp_month' => '09',
            'masked_card' => '4169 **** **** 5678',
            'bank_note' => 'Kart 4169738912345678 ilə ödənildi',
        ]);

        $payload = $payment->refresh()->payload;

        $this->assertArrayNotHasKey('pan', $payload);
        $this->assertArrayNotHasKey('cvv', $payload);
        $this->assertArrayNotHasKey('exp_month', $payload);
        $this->assertSame('4169 **** **** 5678', $payload['masked_card']);
        // Mətn içindəki tam nömrə də maskalanır
        $this->assertStringNotContainsString('4169738912345678', $payload['bank_note']);
        $this->assertStringContainsString('5678', $payload['bank_note']);
    }

    public function test_a_student_can_not_open_someone_elses_fake_gateway_page(): void
    {
        $payment = $this->pendingPayment();

        $other = User::factory()->student()->create();

        $this->actingAs($other)
            ->get(route('payments.fake.show', $payment))
            ->assertForbidden();
    }

    public function test_buying_an_exam_the_student_already_owns_does_not_create_a_payment(): void
    {
        ExamAccess::factory()->create([
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.exams.purchase', $this->exam))
            ->assertRedirect($this->exam->publicUrl());

        $this->assertSame(0, Payment::count());
    }
}
