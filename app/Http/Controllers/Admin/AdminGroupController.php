<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Subject;
use App\Models\SubjectGroupScore;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminGroupController extends Controller
{
    public function index()
    {
        $groups = Group::with(['subjectScores.subject'])->orderBy('number')->get();
        $subjects = Subject::active()->orderBy('order')->get();

        // Bal matrisini hazırla
        $scoreMatrix = [];
        foreach ($subjects as $subject) {
            $scoreMatrix[$subject->id] = [];
            foreach ($groups as $group) {
                $score = SubjectGroupScore::where('subject_id', $subject->id)
                    ->where('group_id', $group->id)
                    ->first();
                $scoreMatrix[$subject->id][$group->id] = $score?->score ?? 0;
            }
        }

        return Inertia::render('Admin/Groups/Index', [
            'groups' => $groups,
            'subjects' => $subjects,
            'scoreMatrix' => $scoreMatrix,
        ]);
    }

    public function updateScores(Request $request, Group $group)
    {
        $validated = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.subject_id' => ['required', 'exists:subjects,id'],
            'scores.*.score' => ['required', 'numeric', 'min:0', 'max:20'],
        ]);

        foreach ($validated['scores'] as $scoreData) {
            SubjectGroupScore::updateOrCreate(
                [
                    'subject_id' => $scoreData['subject_id'],
                    'group_id' => $group->id,
                ],
                ['score' => $scoreData['score']]
            );
        }

        return back()->with('success', 'Ballar uğurla yeniləndi.');
    }
}
