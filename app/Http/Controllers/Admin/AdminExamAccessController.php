<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Exam;
use App\Models\ExamAccess;
use App\Models\User;
use App\Services\Payment\ExamAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * İmtahana girişin admin tərəfindən idarəsi.
 *
 * İlk satışlar köçürmə ilə gedir: pul gələndə admin girişi əl ilə açır və qeydə köçürmənin
 * qəbz nömrəsini/tarixini yazır — sonra hesabat və mübahisəli hallar üçün lazım olur.
 */
class AdminExamAccessController extends Controller
{
    public function __construct(private readonly ExamAccessService $access)
    {
    }

    public function index(Exam $exam): Response
    {
        return Inertia::render('Admin/Exams/Access', [
            'exam' => $exam->load('subject'),
            'accesses' => ExamAccess::with(['user:id,first_name,last_name,email,phone', 'grantedBy:id,first_name,last_name', 'payment:id,status,amount,currency,provider'])
                ->where('exam_id', $exam->id)
                ->latest()
                ->get()
                ->map(fn (ExamAccess $access) => [
                    'id' => $access->id,
                    'student' => [
                        'name' => $access->user?->full_name,
                        'email' => $access->user?->email,
                        'phone' => $access->user?->phone,
                    ],
                    'source' => $access->source,
                    'note' => $access->note,
                    'granted_by' => $access->grantedBy?->full_name,
                    'payment' => $access->payment ? [
                        'status' => $access->payment->status,
                        'amount' => $access->payment->amount,
                        'currency' => $access->payment->currency,
                    ] : null,
                    'expires_at' => $access->expires_at?->toDateTimeString(),
                    'attempts_allowed' => $access->attempts_allowed,
                    'revoked_at' => $access->revoked_at?->toDateTimeString(),
                    'is_active' => $access->isActive(),
                ]),
        ]);
    }

    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            // Şagird email və ya telefon nömrəsi ilə tapılır
            'student' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'attempts_allowed' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'expires_at.after' => 'Bitmə tarixi gələcəkdə olmalıdır.',
        ]);

        $student = $this->findStudent($validated['student']);

        $this->access->grantManually(
            student: $student,
            exam: $exam,
            admin: auth()->user(),
            note: $validated['note'] ?? null,
            expiresAt: isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
            attemptsAllowed: $validated['attempts_allowed'] ?? null,
        );

        return back()->with('success', $student->full_name.' üçün giriş açıldı.');
    }

    public function destroy(Exam $exam, ExamAccess $access): RedirectResponse
    {
        abort_unless($access->exam_id === $exam->id, 404);

        $this->access->revoke($access);

        return back()->with('success', 'Giriş ləğv edildi.');
    }

    /** Email və ya telefon nömrəsi (istənilən formatda) ilə şagirdi tapır. */
    private function findStudent(string $identifier): User
    {
        $identifier = trim($identifier);
        $phone = RegisterRequest::normalizePhone($identifier);

        $student = User::query()
            ->where('email', mb_strtolower($identifier))
            // Telefon yalnız rəqəm daxil ediləndə axtarılır: əks halda "phone = null"
            // şərti telefonsuz hesabları tutardı
            ->when(
                $phone !== null && preg_match(RegisterRequest::PHONE_REGEX, $phone),
                fn ($query) => $query->orWhere('phone', $phone)
            )
            ->first();

        if (! $student || ! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student' => 'Bu email və ya telefon nömrəsi ilə şagird tapılmadı.',
            ]);
        }

        return $student;
    }
}
