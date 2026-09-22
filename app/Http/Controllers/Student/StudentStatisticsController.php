<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAccess;
use App\Services\Statistics\StudentStatistics;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Şagird statistikası: fənn üzrə irəliləyiş, mövzu üzrə zəif yerlər, cəhdlərin qrafiki
 * və alınmış imtahanlar.
 */
class StudentStatisticsController extends Controller
{
    public function __construct(private readonly StudentStatistics $statistics)
    {
    }

    public function index(): Response
    {
        $student = auth()->user();

        return Inertia::render('Student/Statistics', [
            'overview' => $this->statistics->overview($student),
            'subjects' => $this->statistics->subjectProgress($student),
            'topics' => $this->statistics->topicBreakdown($student),
            'timeline' => $this->statistics->timeline($student),
            'purchases' => $this->purchases($student),
            'minTopicAnswers' => StudentStatistics::MIN_TOPIC_ANSWERS,
        ]);
    }

    /**
     * Şagirdin giriş hüququ olan imtahanlar (ödəniş, admin icazəsi və ya pulsuz).
     *
     * @return array<int, array<string, mixed>>
     */
    private function purchases($student): array
    {
        return ExamAccess::query()
            ->with('exam:id,slug,title,price,is_free')
            ->where('user_id', $student->id)
            ->whereNull('revoked_at')
            ->latest()
            ->get()
            ->filter(fn (ExamAccess $access) => $access->exam !== null)
            ->map(fn (ExamAccess $access) => [
                'exam_id' => $access->exam_id,
                'title' => $access->exam->title,
                'url' => $access->exam->publicUrl(),
                'source' => $access->source,
                'granted_at' => $access->created_at?->format('d.m.Y'),
                'expires_at' => $access->expires_at?->format('d.m.Y'),
                'is_active' => $access->isActive(),
            ])
            ->values()
            ->all();
    }
}
