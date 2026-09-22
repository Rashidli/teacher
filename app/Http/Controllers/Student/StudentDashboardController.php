<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\Statistics\StudentStatistics;
use Inertia\Inertia;

class StudentDashboardController extends Controller
{
    public function __construct(private readonly StudentStatistics $statistics)
    {
    }

    public function index()
    {
        $user = auth()->user();

        // Göstəricilər statistika səhifəsi ilə eyni mənbədən gəlir: nisbi bal (100-lük),
        // bir onluğa yuvarlaqlaşdırılmış. Əvvəl burada xam SQL AVG işlənirdi və ekranda
        // "45.000000" kimi görünürdü; ən yüksək bal və düzgün cavab faizi isə ümumiyyətlə
        // göndərilmirdi (səhifə həmişə 0 göstərirdi).
        $overview = $this->statistics->overview($user);

        $stats = [
            'totalAttempts' => $overview['attempts'],
            'inProgressExams' => $user->examAttempts()->inProgress()->count(),
            'averageScore' => $overview['average_relative'] ?? 0,
            'highestScore' => $overview['best_relative'] ?? 0,
            'correctPercentage' => $overview['correct_percentage'] ?? 0,
        ];

        $attempts = $user->examAttempts()
            ->with('exam:id,slug,title,duration_minutes')
            ->latest()
            ->get()
            ->filter(fn (ExamAttempt $attempt) => $attempt->exam !== null);

        return Inertia::render('Student/Dashboard', [
            'stats' => $stats,
            'inProgress' => $attempts
                ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
                ->filter(fn (ExamAttempt $attempt) => $attempt->remaining_time > 0)
                ->map(fn (ExamAttempt $attempt) => [
                    'id' => $attempt->id,
                    'title' => $attempt->exam->title,
                    'url' => route('student.exams.attempt', $attempt),
                    'remaining_minutes' => (int) ceil($attempt->remaining_time / 60),
                ])
                ->values(),
            // Son beş nəticə: bal nisbi ölçüdədir (100-lük), statistika səhifəsi ilə eyni
            'recentResults' => $attempts
                ->whereIn('status', [ExamAttempt::STATUS_COMPLETED, ExamAttempt::STATUS_TIMED_OUT])
                ->take(5)
                ->map(fn (ExamAttempt $attempt) => [
                    'id' => $attempt->id,
                    'title' => $attempt->exam->title,
                    'url' => route('student.exams.result', $attempt),
                    'finished_at' => $attempt->finished_at?->format('d.m.Y'),
                    'relative_score' => $attempt->relative_score,
                    'correct_answers' => $attempt->correct_answers,
                    'question_count' => $attempt->correct_answers + $attempt->wrong_answers + $attempt->unanswered,
                ])
                ->values(),
        ]);
    }
}
