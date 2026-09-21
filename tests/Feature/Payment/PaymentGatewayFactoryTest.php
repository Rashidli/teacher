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
 * Sınaq provayderi produksiyada işləməməlidir: əks halda kimsə saxta "Uğurlu" düyməsi ilə
 * pulsuz giriş əldə edə bilərdi.
 */
class PaymentGatewayFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_fake_gateway_is_used_outside_production(): void
    {
        config(['payments.driver' => 'fake']);

        $this->assertInstanceOf(FakePaymentGateway::class, app(PaymentGatewayFactory::class)->make());
        $this->assertTrue(app(PaymentGatewayFactory::class)->available());
    }

    public function test_the_fake_gateway_is_refused_in_production(): void
    {
        config(['payments.driver' => 'fake']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);

        app(PaymentGatewayFactory::class)->make();
    }

    public function test_purchases_are_reported_unavailable_in_production_with_the_fake_driver(): void
    {
        config(['payments.driver' => 'fake']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertFalse(app(PaymentGatewayFactory::class)->available());
    }

    /** UI-da "Al" düyməsi göstərilməsin deyə səhifəyə də ötürülür. */
    public function test_the_exam_page_reports_that_purchases_are_unavailable(): void
    {
        config(['payments.driver' => 'fake']);
        $this->app->detectEnvironment(fn () => 'production');

        $student = User::factory()->student()->create();
        $exam = Exam::factory()->published()->paid()->create();

        $this->actingAs($student, 'student')
            ->get(route('student.exams.show', $exam))
            ->assertInertia(fn ($page) => $page->where('purchasesEnabled', false));
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
