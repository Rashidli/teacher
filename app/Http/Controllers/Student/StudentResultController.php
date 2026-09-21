<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class StudentResultController extends Controller
{
    public function index()
    {
        $attempts = auth()->user()->examAttempts()
            ->with(['exam.subject', 'exam.teacher', 'group'])
            ->completed()
            ->latest()
            ->paginate(15);

        return Inertia::render('Student/Results/Index', [
            'attempts' => $attempts,
        ]);
    }
}
