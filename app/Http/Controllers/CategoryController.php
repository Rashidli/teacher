<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Subject;
use App\Support\Localization;
use Inertia\Inertia;
use Inertia\Response;

/**
 * İctimai kateqoriya səhifəsi.
 *
 * Route `/{path}` bütün digər route-lardan SONRA qeydiyyatdan keçir (routes/web.php sonu),
 * ona görə /login, /admin/… kimi ünvanlar buraya düşmür — onlar əvvəl uyğunlaşır.
 */
class CategoryController extends Controller
{
    public function show(string $path): Response
    {
        $category = Category::active()
            ->where('path', trim($path, '/'))
            ->firstOr(fn () => abort(404));

        $category->load([
            'children' => fn ($query) => $query->where('is_active', true),
            'subjects',
            'group',
        ]);

        return Inertia::render('Category/Show', [
            'category' => [
                'name' => $category->localized('name'),
                'short' => $category->localized('short'),
                'description' => $category->localized('description'),
                'h1' => $category->localized('h1') ?: $category->localized('name'),
                'intro' => $category->localized('intro'),
                'has_exams' => $category->has_exams,
                'url' => Localization::categoryUrl($category->path),
            ],
            'breadcrumb' => $category->ancestors()
                ->push($category)
                ->map(fn (Category $node) => [
                    'name' => $node->localized('name'),
                    'url' => Localization::categoryUrl($node->path),
                ])
                ->values(),
            'children' => $category->children->map(fn (Category $child) => [
                'name' => $child->localized('name'),
                'short' => $child->localized('short'),
                'url' => Localization::categoryUrl($child->path),
            ]),
            'subjects' => $category->subjects->map(fn (Subject $subject) => [
                'name' => $subject->name,
                'question_count' => $subject->pivot->question_count,
                'max_score' => $category->maxScoreFor($subject),
            ]),
            'exams' => $this->exams($category),
            'seo' => [
                'title' => $category->localized('seo_title') ?: $category->localized('name'),
                'description' => $category->localized('seo_description'),
            ],
        ]);
    }

    /** Kateqoriyanın özünün və bütün alt düyünlərinin satışdakı imtahanları */
    private function exams(Category $category)
    {
        return Exam::query()
            ->with(['subject:id,name', 'category:id,name,path'])
            ->withCount('questions')
            ->whereIn('category_id', $category->subtreeIds())
            ->where('is_published', true)
            ->where('is_active', true)
            ->latest()
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject?->name,
                'category' => $exam->category?->name,
                'duration_minutes' => $exam->duration_minutes,
                'questions_count' => $exam->questions_count,
                'is_free' => $exam->is_free,
                'price' => $exam->price,
            ]);
    }
}
