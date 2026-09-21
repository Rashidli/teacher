<?php

namespace App\Services\Payment;

use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * İmtahana giriş hüququ: kimin hansı imtahanı aça biləcəyi.
 *
 * Bir şagird + bir imtahan üçün yalnız bir sətir olur (unique). Təkrar alışda yeni sətir
 * yaradılmır — mövcud sətir yenilənir və müddəti uzadılır.
 */
class ExamAccessService
{
    /** Pulsuz imtahanlar hamıya açıqdır; qalanları üçün aktiv giriş lazımdır. */
    public function allows(User $user, Exam $exam): bool
    {
        return $exam->is_free || $this->activeAccess($user, $exam) !== null;
    }

    public function activeAccess(User $user, Exam $exam): ?ExamAccess
    {
        return ExamAccess::query()
            ->where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->active()
            ->first();
    }

    /** Ödəniş uğurlu olandan sonra giriş açılır (PaymentProcessor tranzaksiyası daxilində). */
    public function grantFromPayment(Payment $payment): ExamAccess
    {
        $exam = $payment->purchasable;

        if (! $exam instanceof Exam) {
            throw new \RuntimeException('Bu ödəniş imtahan alışı deyil.');
        }

        return $this->grant(
            user: $payment->user,
            exam: $exam,
            source: ExamAccess::SOURCE_PAYMENT,
            payment: $payment,
        );
    }

    /** Köçürmə ilə ödəyənlər üçün: admin girişi əl ilə açır. */
    public function grantManually(
        User $student,
        Exam $exam,
        ?User $admin = null,
        ?string $note = null,
        ?CarbonInterface $expiresAt = null,
        ?int $attemptsAllowed = null,
    ): ExamAccess {
        return $this->grant(
            user: $student,
            exam: $exam,
            source: ExamAccess::SOURCE_MANUAL,
            grantedBy: $admin,
            note: $note,
            expiresAt: $expiresAt,
            attemptsAllowed: $attemptsAllowed,
        );
    }

    public function revoke(ExamAccess $access, ?string $note = null): ExamAccess
    {
        $access->update([
            'revoked_at' => now(),
            'note' => $note ?? $access->note,
        ]);

        return $access;
    }

    /** Ödəniş geri qaytarılanda giriş avtomatik bağlanır. */
    public function revokeForPayment(Payment $payment): void
    {
        ExamAccess::query()
            ->where('payment_id', $payment->id)
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (ExamAccess $access) => $this->revoke($access));
    }

    /**
     * Girişi yaradır və ya mövcud sətri yeniləyir.
     *
     * `$expiresAt` verilməyibsə müddət config('payments.access_valid_days')-dan hesablanır:
     * null olarsa giriş müddətsizdir. Mövcud müddət hələ bitməyibsə onun üstünə əlavə olunur,
     * bitibsə bu andan başlayır.
     */
    private function grant(
        User $user,
        Exam $exam,
        string $source,
        ?Payment $payment = null,
        ?User $grantedBy = null,
        ?string $note = null,
        ?CarbonInterface $expiresAt = null,
        ?int $attemptsAllowed = null,
    ): ExamAccess {
        return DB::transaction(function () use ($user, $exam, $source, $payment, $grantedBy, $note, $expiresAt, $attemptsAllowed) {
            $access = ExamAccess::query()
                ->where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'source' => $source,
                'payment_id' => $payment?->id ?? $access?->payment_id,
                'granted_by' => $grantedBy?->id ?? $access?->granted_by,
                'note' => $note ?? $access?->note,
                'attempts_allowed' => $attemptsAllowed ?? $access?->attempts_allowed,
                'expires_at' => $this->resolveExpiry($access, $expiresAt),
                // Yenidən alış və ya yeni icazə ləğvi götürür
                'revoked_at' => null,
            ];

            if ($access) {
                $access->update($attributes);

                return $access;
            }

            return ExamAccess::create($attributes + [
                'user_id' => $user->id,
                'exam_id' => $exam->id,
            ]);
        });
    }

    private function resolveExpiry(?ExamAccess $access, ?CarbonInterface $expiresAt): ?CarbonInterface
    {
        if ($expiresAt !== null) {
            return $expiresAt;
        }

        $days = config('payments.access_valid_days');

        if ($days === null) {
            return null;
        }

        // Hələ bitməmiş müddətin üstünə əlavə olunur, bitibsə bu andan başlayır
        $base = $access?->expires_at?->isFuture() ? $access->expires_at : now();

        return $base->copy()->addDays((int) $days);
    }
}
