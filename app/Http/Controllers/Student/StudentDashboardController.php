<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
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

        $recentAttempts = $user->examAttempts()
            ->with(['exam.subject', 'exam.teacher', 'group'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Student/Dashboard', [
            'stats' => $stats,
            'recentAttempts' => $recentAttempts,
        ]);
    }
}
