<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Payment\ExamAccessService;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentProcessor;
use Illuminate\Http\RedirectResponse;

/**
 * "Al" düyməsi: gözləyən ödəniş yaradılır və şagird provayderin səhifəsinə yönləndirilir.
 *
 * TEST REJİMİ (`PAYMENT_DRIVER=fake`): bank səhifəsi açılmır — ödəniş dərhal təsdiqlənir,
 * giriş açılır və şagird imtahan səhifəsinə qayıdır. Real pul hərəkət etmir; ödəniş qeydində
 * `provider = fake` və `payload.test_mode = true` qalır.
 */
class StudentPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly PaymentProcessor $payments,
        private readonly ExamAccessService $access,
    ) {}

    public function store(Exam $exam): RedirectResponse
    {
        $student = auth()->user();

        abort_unless($exam->is_published && $exam->is_active, 404);

        if ($exam->is_free || $this->access->allows($student, $exam)) {
            return redirect()->to($exam->publicUrl())
                ->with('success', 'Bu imtahan artıq sizə açıqdır.');
        }

        $gateway = $this->gateways->make();

        $payment = $this->payments->startExamPurchase($student, $exam, $gateway);

        // Test rejimi: bank yoxdur, ödəniş elə burada "paid" olur və giriş açılır
        if ($gateway instanceof FakePaymentGateway) {
            $this->payments->handleCallback($payment, $gateway->confirm($payment));

            return redirect()->to($exam->publicUrl())
                ->with('success', __('exam_page.test_mode_paid'));
        }

        return redirect()->away($gateway->redirectUrl($payment));
    }
}
