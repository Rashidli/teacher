<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Group;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kateqoriya ağacının idarəsi.
 *
 * `path` dəyişəndə bütün alt düyünlərin yolları da yenilənir — əks halda URL-lər qırılardı.
 */
class AdminCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::query()
                ->withCount(['children', 'exams'])
                ->orderBy('path')
                ->get(['id', 'parent_id', 'name', 'path', 'slug', 'is_active', 'has_exams', 'order', 'group_id'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'path' => $category->path,
                    'depth' => substr_count($category->path, '/'),
                    'is_active' => $category->is_active,
                    'has_exams' => $category->has_exams,
                    'children_count' => $category->children_count,
                    'exams_count' => $category->exams_count,
                    'group_id' => $category->group_id,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Category::create($validated + ['path' => $this->buildPath($validated)]);

        return redirect()->route('admin.categories.index')->with('success', 'Kateqoriya yaradıldı.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Edit', $this->formData($category) + [
            'category' => $category->only([
                'id', 'parent_id', 'group_id', 'slug', 'path', 'name', 'short', 'description',
                'is_active', 'has_exams', 'order', 'seo_title', 'seo_description', 'h1', 'intro',
            ]),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validated($request, $category);

        $oldPath = $category->path;
        $newPath = $this->buildPath($validated, $category);

        $category->update($validated + ['path' => $newPath]);

        if ($oldPath !== $newPath) {
            $this->repathDescendants($oldPath, $newPath);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Kateqoriya yeniləndi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->withErrors(['category' => 'Alt kateqoriyası olan düyün silinə bilməz.']);
        }

        if ($category->exams()->exists()) {
            return back()->withErrors(['category' => 'Bu kateqoriyada imtahanlar var, əvvəlcə onları köçürün.']);
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Kateqoriya silindi.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id'),
                // Düyün öz alt ağacına köçürülə bilməz (dövr yaranardı)
                Rule::notIn($category ? $category->subtreeIds() : []),
            ],
            'group_id' => ['nullable', Rule::exists('groups', 'id')],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'short' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'has_exams' => ['boolean'],
            'order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'h1' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:5000'],
        ], [
            'slug.regex' => 'Slug yalnız kiçik latın hərfləri, rəqəm və defisdən ibarət olmalıdır (məs: 1-ci-qrup).',
            'parent_id.not_in' => 'Kateqoriya öz alt kateqoriyasının altına köçürülə bilməz.',
        ]);
    }

    private function buildPath(array $validated, ?Category $category = null): string
    {
        $parentPath = $validated['parent_id']
            ? Category::whereKey($validated['parent_id'])->value('path')
            : null;

        $path = trim(($parentPath ? $parentPath.'/' : '').$validated['slug'], '/');

        // Yol unikaldır: eyni yol başqa düyündədirsə sonuna nömrə əlavə olunur
        $query = Category::where('path', $path);

        if ($category) {
            $query->whereKeyNot($category->id);
        }

        return $query->exists() ? $path.'-'.Str::random(4) : $path;
    }

    /** Valideynin yolu dəyişəndə alt ağacın yolları da yenilənir */
    private function repathDescendants(string $oldPath, string $newPath): void
    {
        Category::where('path', 'like', $oldPath.'/%')
            ->get()
            ->each(function (Category $descendant) use ($oldPath, $newPath) {
                $descendant->update([
                    'path' => $newPath.substr($descendant->path, strlen($oldPath)),
                ]);
            });
    }

    private function formData(?Category $category = null): array
    {
        return [
            'parents' => Category::orderBy('path')
                ->when($category, fn ($query) => $query->whereNotIn('id', $category->subtreeIds()))
                ->get(['id', 'name', 'path'])
                ->map(fn (Category $parent) => [
                    'id' => $parent->id,
                    'label' => $parent->path.' — '.$parent->name,
                ]),
            'groups' => Group::orderBy('code')->get(['id', 'code', 'name']),
            'subjects' => Subject::active()->orderBy('order')->get(['id', 'name']),
        ];
    }
}
