<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Etiketlərin idarəsi.
 *
 * Sinif etiketləri (`grade`) `TagSeeder` ilə gəlir, amma buradan da yaradıla və
 * redaktə oluna bilər. Sərbəst etiketləri (`other`) tamamilə admin qurur.
 *
 * SİLMƏ: imtahana bağlı etiket silinmir — əvəzinə deaktiv edilir, əks halda kataloq
 * filtri ilə mövcud imtahanlar arasındakı əlaqə səssizcə itərdi.
 */
class AdminTagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Tags/Index', [
            'tags' => Tag::withCount('exams')
                ->ordered()
                ->get()
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'slug' => $tag->slug,
                    'name' => $tag->name,
                    'kind' => $tag->kind,
                    'order' => $tag->order,
                    'is_active' => $tag->is_active,
                    'exams_count' => $tag->exams_count,
                ]),
            'kinds' => Tag::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Tag::create($validated + ['slug' => $this->slug($validated['name'])]);

        return back()->with('success', 'Etiket əlavə edildi.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $tag->update($this->validated($request, $tag));

        return back()->with('success', 'Etiket yeniləndi.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        if ($tag->exams()->exists()) {
            return back()->with('error', 'Bu etiket imtahanlara bağlıdır — silmək əvəzinə deaktiv edin.');
        }

        $tag->delete();

        return back()->with('success', 'Etiket silindi.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Tag $tag = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(Tag::KINDS)],
            'order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], [
            'kind.in' => 'Belə etiket qrupu yoxdur.',
        ]);
    }

    /** Ad dəyişəndə də ünvan sabit qalsın deyə slug yalnız YARADILANDA qurulur */
    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'etiket';
        $slug = $base;

        for ($i = 2; Tag::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
