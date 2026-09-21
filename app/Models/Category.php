<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * İmtahan kateqoriyası (iyerarxik).
 *
 * `path` URL yoludur və valideyn zəncirindən avtomatik hesablanır, amma əl ilə də təyin
 * oluna bilər — mövcud ünvanları qorumaq üçün (MİQ "Müəllimlər"in altındadır, URL-i `/miq`).
 *
 * Bal kateqoriyada saxlanılmır: qrupa bağlı düyünlərdə `subject_group_scores`-dan gəlir.
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'group_id', 'slug', 'path', 'name', 'short', 'description',
        'is_active', 'has_exams', 'order', 'seo_title', 'seo_description', 'h1', 'intro',
        'translations',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_exams' => 'boolean',
        'order' => 'integer',
        'translations' => 'array',
    ];

    /**
     * Mətni cari dildə qaytarır. Tərcümə yoxdursa Azərbaycan variantı göstərilir —
     * yeni kateqoriyalar tərcümə olunana qədər səhifə boş qalmasın.
     */
    public function localized(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if ($locale !== config('locales.default')) {
            $translated = data_get($this->translations, $locale.'.'.$field);

            if (filled($translated)) {
                return $translated;
            }
        }

        return $this->{$field};
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (blank($category->path)) {
                $category->path = $category->buildPath();
            }
        });
    }

    /** Valideyn zəncirindən yol qurur: "abituriyent/1-ci-qrup/rk" */
    public function buildPath(): string
    {
        $parentPath = $this->parent_id
            ? static::whereKey($this->parent_id)->value('path')
            : null;

        return $parentPath ? $parentPath.'/'.$this->slug : (string) $this->slug;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order')->orderBy('name');
    }

    /** Bal qrupu (yalnız abituriyent düyünlərində) */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)
            ->withPivot(['question_count', 'options_per_question', 'max_score', 'order'])
            ->withTimestamps()
            ->orderBy('category_subject.order');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** Kök düyündən bu düyünə qədər zəncir (breadcrumb üçün) */
    public function ancestors(): Collection
    {
        $chain = new Collection;

        for ($node = $this->parent; $node !== null; $node = $node->parent) {
            $chain->prepend($node);
        }

        return $chain;
    }

    /** Bu düyün və bütün alt düyünlərinin ID-ləri (imtahanları toplamaq üçün) */
    public function subtreeIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        while ($level !== []) {
            $level = static::whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    /**
     * Fənnin bu kateqoriyadakı maksimal balı.
     * Pivotda göstərilməyibsə və kateqoriya qrupa bağlıdırsa, bal matrisindən götürülür.
     */
    public function maxScoreFor(Subject $subject): ?float
    {
        $pivotScore = $this->subjects->firstWhere('id', $subject->id)?->pivot?->max_score;

        if ($pivotScore !== null) {
            return (float) $pivotScore;
        }

        $group = $this->group?->scoringGroup();

        if ($group === null) {
            return null;
        }

        $score = SubjectGroupScore::where('subject_id', $subject->id)
            ->where('group_id', $group->id)
            ->value('max_score');

        return $score === null ? null : (float) $score;
    }
}
