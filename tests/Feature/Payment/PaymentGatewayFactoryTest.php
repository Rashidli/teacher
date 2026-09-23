<?php

namespace Tests\Feature\Payment;

use App\Models\Exam;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Provayder seçimi TƏK açarla idarə olunur: `PAYMENT_DRIVER`.
 *
 * `fake` test rejimidir və produksiyada da işləyir (sayt müvəqqəti subdomendədir).
 * Real bank gələndə dəyişən yeni driverə çevrilir və test rejimi bir addımla sönür —
 * öz domenimizə keçməzdən əvvəl bu MÜTLƏQ edilməlidir (bax ROADMAP P2.5).
 */
class PaymentGatewayFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_fake_gateway_is_used_when_the_driver_says_so(): void
    {
        config(['payments.driver' => 'fake']);

        $this->assertInstanceOf(FakePaymentGateway::class, app(PaymentGatewayFactory::class)->make());
        $this->assertTrue(app(PaymentGatewayFactory::class)->available());
        $this->assertTrue(app(PaymentGatewayFactory::class)->testMode());
    }

    /** Müvəqqəti subdomen: fake driver produksiyada da işləyir. */
    public function test_the_fake_gateway_also_works_in_production(): void
    {
        config(['payments.driver' => 'fake']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertInstanceOf(FakePaymentGateway::class, app(PaymentGatewayFactory::class)->make());
        $this->assertTrue(app(PaymentGatewayFactory::class)->available());
    }

    /** Səhifə test rejimini bildirir: "real ödəniş getmir" xəbərdarlığı ondan asılıdır. */
    public function test_the_exam_page_reports_test_mode(): void
    {
        config(['payments.driver' => 'fake']);

        $student = User::factory()->student()->create();
        $exam = Exam::factory()->published()->paid()->create();

        $this->actingAs($student)
            ->get($exam->publicUrl())
            ->assertInertia(fn ($page) => $page
                ->where('purchasesEnabled', true)
                ->where('paymentsTestMode', true));
    }

    public function test_an_unknown_driver_is_refused(): void
    {
        config(['payments.driver' => 'hech-bir-bank']);

        $this->expectException(RuntimeException::class);

        app(PaymentGatewayFactory::class)->make();
    }

    /** Ödəniş statusu geri qayıtmır. */
    public function test_a_paid_payment_can_only_move_to_refunded(): void
    {
        $payment = Payment::factory()->paid()->create();

        $this->assertTrue($payment->canTransitionTo(Payment::STATUS_REFUNDED));
        $this->assertFalse($payment->canTransitionTo(Payment::STATUS_PENDING));
        $this->assertFalse($payment->canTransitionTo(Payment::STATUS_FAILED));

        $this->expectException(RuntimeException::class);
        $payment->transitionTo(Payment::STATUS_PENDING);
    }
}
