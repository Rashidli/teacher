<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class TeacherDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $stats = [
            'totalExams' => $user->exams()->count(),
            'publishedExams' => $user->exams()->published()->count(),
            'totalQuestions' => $user->exams()->withCount('questions')->get()->sum('questions_count'),
            'subjects' => $user->subjects()->count(),
        ];

        $recentExams = $user->exams()
            ->with(['subject', 'group'])
            ->withCount('questions')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Teacher/Dashboard', [
            'stats' => $stats,
            'recentExams' => $recentExams,
        ]);
    }
}
