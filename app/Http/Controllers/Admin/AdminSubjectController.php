<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminSubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::withCount(['exams', 'teachers'])
            ->orderBy('order')
            ->get();

        return Inertia::render('Admin/Subjects/Index', [
            'subjects' => $subjects,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:humanitarian,technical'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['order'] = Subject::max('order') + 1;

        Subject::create($validated);

        return back()->with('success', 'Fənn uğurla yaradıldı.');
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:humanitarian,technical'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $subject->update($validated);

        return back()->with('success', 'Fənn uğurla yeniləndi.');
    }

    public function toggleActive(Subject $subject)
    {
        $subject->update(['is_active' => !$subject->is_active]);

        return back()->with('success', 'Fənn statusu dəyişdirildi.');
    }
}
