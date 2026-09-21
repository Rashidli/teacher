<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fənn mövzuları. Rüb (1–4) yalnız məktəb fənlərində doldurulur; sürücülük və dövlət
 * qulluğu mövzularında boş qalır.
 */
class AdminTopicController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Topics/Index', [
            'topics' => Topic::with('subject:id,name')
                ->withCount('questions')
                ->when($request->subject_id, fn ($query, $id) => $query->where('subject_id', $id))
                ->orderBy('subject_id')->orderBy('quarter')->orderBy('order')->orderBy('name')
                ->get()
                ->map(fn (Topic $topic) => [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'subject' => $topic->subject?->name,
                    'subject_id' => $topic->subject_id,
                    'quarter' => $topic->quarter,
                    'order' => $topic->order,
                    'is_active' => $topic->is_active,
                    'questions_count' => $topic->questions_count,
                ]),
            'subjects' => Subject::active()->orderBy('order')->get(['id', 'name']),
            'filters' => $request->only(['subject_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $topic = Topic::create($this->validated($request));

        return back()->with('success', "\"{$topic->name}\" mövzusu əlavə edildi.");
    }

    public function update(Request $request, Topic $topic): RedirectResponse
    {
        $topic->update($this->validated($request, $topic));

        return back()->with('success', 'Mövzu yeniləndi.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        if ($topic->questions()->exists()) {
            return back()->withErrors([
                'topic' => 'Bu mövzuda suallar var. Əvvəlcə onları başqa mövzuya keçirin.',
            ]);
        }

        $topic->delete();

        return back()->with('success', 'Mövzu silindi.');
    }

    private function validated(Request $request, ?Topic $topic = null): array
    {
        $validated = $request->validate([
            'subject_id' => ['required', Rule::exists('subjects', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]);

        $slug = Str::slug($validated['name']);

        // Slug fənn daxilində unikaldır
        $taken = Topic::where('subject_id', $validated['subject_id'])
            ->where('slug', $slug)
            ->when($topic, fn ($query) => $query->whereKeyNot($topic->id))
            ->exists();

        $validated['slug'] = $taken ? $slug.'-'.Str::random(4) : $slug;
        $validated['order'] ??= 0;

        return $validated;
    }
}
