<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\User;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $teachersEnabled = (bool) config('features.teachers');

        $stats = [
            'totalStudents' => User::role('student')->count(),
            'totalExams' => Exam::count(),
            'publishedExams' => Exam::published()->count(),
        ];

        $recentTeachers = [];

        // Müəllim statistikası yalnız müəllim modulu aktiv olanda hesablanır
        if ($teachersEnabled) {
            $stats += [
                'totalTeachers' => User::role('teacher')->count(),
                'verifiedTeachers' => User::verifiedTeachers()->count(),
                'pendingTeachers' => User::role('teacher')
                    ->whereHas('teacherProfile', fn($q) => $q->where('is_verified', false))
                    ->count(),
            ];

            $recentTeachers = User::role('teacher')
                ->with('teacherProfile', 'subjects')
                ->latest()
                ->take(5)
                ->get();
        }

        $recentExams = Exam::with(['teacher', 'subject', 'group'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentTeachers' => $recentTeachers,
            'recentExams' => $recentExams,
        ]);
    }
}
