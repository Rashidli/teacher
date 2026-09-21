<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminTeacherController extends Controller
{
    public function index(Request $request)
    {
        $query = User::role('teacher')
            ->with(['teacherProfile', 'subjects']);

        if ($request->status === 'verified') {
            $query->whereHas('teacherProfile', fn($q) => $q->where('is_verified', true));
        } elseif ($request->status === 'pending') {
            $query->whereHas('teacherProfile', fn($q) => $q->where('is_verified', false));
        }

        $teachers = $query->latest()->paginate(15);

        return Inertia::render('Admin/Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $request->only('status'),
        ]);
    }

    public function show(User $teacher)
    {
        $teacher->load(['teacherProfile', 'subjects', 'exams' => function ($q) {
            $q->with(['subject', 'group'])->withCount('questions')->latest();
        }]);

        return Inertia::render('Admin/Teachers/Show', [
            'teacher' => $teacher,
        ]);
    }

    public function verify(User $teacher)
    {
        $teacher->teacherProfile->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        return back()->with('success', 'Müəllim uğurla təsdiqləndi.');
    }

    public function unverify(User $teacher)
    {
        $teacher->teacherProfile->update([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        return back()->with('success', 'Müəllimin təsdiqi ləğv edildi.');
    }
}
