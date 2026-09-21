<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SitemapController;
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
 * Eyni qayda rusca ünvana (`ru_path`) da aiddir.
 *
 * SEO mətnləri (title, meta description, H1, giriş mətni) hər iki dildə buradan redaktə olunur:
 * Azərbaycan variantı sütunlarda, rus variantı `translations` JSON-unda saxlanılır.
 */
class AdminCategoryController extends Controller
{
    /** Rus dilinə tərcümə olunan sahələr (`translations->ru`) */
    private const TRANSLATABLE = ['name', 'short', 'description', 'seo_title', 'seo_description', 'h1', 'intro'];

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

        SitemapController::forget();

        return redirect()->route('admin.categories.index')->with('success', 'Kateqoriya yaradıldı.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Edit', $this->formData($category) + [
            'category' => $category->only([
                'id', 'parent_id', 'group_id', 'slug', 'path', 'ru_path', 'name', 'short', 'description',
                'is_active', 'has_exams', 'ru_enabled', 'order', 'seo_title', 'seo_description', 'h1', 'intro',
            ]) + ['translations' => $this->russianFields($category)],
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validated($request, $category);

        $oldPath = $category->path;
        $oldRuPath = $category->ru_path;
        $newPath = $this->buildPath($validated, $category);

        $category->update($validated + ['path' => $newPath]);

        if ($oldPath !== $newPath) {
            $this->repathDescendants('path', $oldPath, $newPath);
        }

        if ($oldRuPath && $category->ru_path && $oldRuPath !== $category->ru_path) {
            $this->repathDescendants('ru_path', $oldRuPath, $category->ru_path);
        }

        SitemapController::forget();

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

        SitemapController::forget();

        return redirect()->route('admin.categories.index')->with('success', 'Kateqoriya silindi.');
    }

    /** Formaya rus mətnləri `translations` JSON-undan düz açarlarla verilir */
    private function russianFields(Category $category): array
    {
        $fields = [];

        foreach (self::TRANSLATABLE as $field) {
            $fields[$field] = data_get($category->translations, 'ru.'.$field);
        }

        return $fields;
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $validated = $request->validate([
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
            'ru_enabled' => ['boolean'],
            // Rusca ünvan: boş olanda səhifə Azərbaycan yolu ilə açılır
            'ru_path' => [
                'nullable', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/',
                Rule::unique('categories', 'ru_path')->ignore($category?->id),
            ],
            'translations' => ['nullable', 'array'],
            'translations.name' => ['nullable', 'string', 'max:255'],
            'translations.short' => ['nullable', 'string', 'max:255'],
            'translations.description' => ['nullable', 'string', 'max:2000'],
            'translations.seo_title' => ['nullable', 'string', 'max:255'],
            'translations.seo_description' => ['nullable', 'string', 'max:500'],
            'translations.h1' => ['nullable', 'string', 'max:255'],
            'translations.intro' => ['nullable', 'string', 'max:5000'],
        ], [
            'slug.regex' => 'Slug yalnız kiçik latın hərfləri, rəqəm və defisdən ibarət olmalıdır (məs: 1-ci-qrup).',
            'ru_path.regex' => 'Rusca ünvan yalnız kiçik latın hərfləri, rəqəm, defis və "/" ola bilər (məs: abiturient/1-ya-gruppa).',
            'ru_path.unique' => 'Bu rusca ünvan başqa kateqoriyada işlənir.',
            'parent_id.not_in' => 'Kateqoriya öz alt kateqoriyasının altına köçürülə bilməz.',
        ]);

        // Rus mətnləri `translations` JSON-unda "ru" açarının altında saxlanılır
        $russian = array_filter($validated['translations'] ?? [], fn ($value) => filled($value));
        $validated['translations'] = $russian === [] ? null : ['ru' => $russian];

        return $validated;
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

    /** Valideynin yolu dəyişəndə alt ağacın yolları da yenilənir (həm az, həm ru) */
    private function repathDescendants(string $column, string $oldPath, string $newPath): void
    {
        Category::where($column, 'like', $oldPath.'/%')
            ->get()
            ->each(function (Category $descendant) use ($column, $oldPath, $newPath) {
                $descendant->update([
                    $column => $newPath.substr($descendant->{$column}, strlen($oldPath)),
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
