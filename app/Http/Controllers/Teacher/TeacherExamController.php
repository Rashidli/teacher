<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamRequest;
use App\Models\Exam;
use App\Models\Group;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeacherExamController extends Controller
{
    public function index()
    {
        $exams = auth()->user()->exams()
            ->with(['subject', 'group'])
            ->withCount('questions')
            ->latest()
            ->paginate(10);

        return Inertia::render('Teacher/Exams/Index', [
            'exams' => $exams,
        ]);
    }

    public function create()
    {
        $subjects = auth()->user()->subjects()->active()->get();
        $groups = Group::active()->orderBy('number')->get();

        return Inertia::render('Teacher/Exams/Create', [
            'subjects' => $subjects,
            'groups' => $groups,
        ]);
    }

    public function store(StoreExamRequest $request)
    {
        $exam = auth()->user()->exams()->create($request->validated());

        return redirect()->route('teacher.exams.show', $exam)
            ->with('success', 'İmtahan uğurla yaradıldı. İndi suallar əlavə edə bilərsiniz.');
    }

    public function show(Exam $exam)
    {
        $this->authorize('view', $exam);

        $exam->load(['subject', 'group', 'questions.options']);

        return Inertia::render('Teacher/Exams/Show', [
            'exam' => $exam,
        ]);
    }

    public function edit(Exam $exam)
    {
        $this->authorize('update', $exam);

        $subjects = auth()->user()->subjects()->active()->get();
        $groups = Group::active()->orderBy('number')->get();

        return Inertia::render('Teacher/Exams/Edit', [
            'exam' => $exam,
            'subjects' => $subjects,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $exam->update($validated);

        return redirect()->route('teacher.exams.show', $exam)
            ->with('success', 'İmtahan uğurla yeniləndi.');
    }

    public function destroy(Exam $exam)
    {
        $this->authorize('delete', $exam);

        $exam->delete();

        return redirect()->route('teacher.exams.index')
            ->with('success', 'İmtahan uğurla silindi.');
    }
}
