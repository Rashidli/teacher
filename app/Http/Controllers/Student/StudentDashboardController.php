<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class StudentDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $stats = [
            'totalAttempts' => $user->examAttempts()->count(),
            'completedExams' => $user->examAttempts()->completed()->count(),
            'inProgressExams' => $user->examAttempts()->inProgress()->count(),
            'averageScore' => $user->examAttempts()->completed()->avg('total_score') ?? 0,
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
