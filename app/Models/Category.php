<?php

namespace App\Models;

use App\Support\GradeMention;
use App\Support\Localization;
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
        'parent_id', 'group_id', 'slug', 'path', 'ru_path', 'name', 'short', 'description',
        'is_active', 'has_exams', 'ru_enabled', 'order', 'color',
        'seo_title', 'seo_description', 'h1', 'intro', 'translations',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_exams' => 'boolean',
        'ru_enabled' => 'boolean',
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

    /**
     * Dilə görə ünvan hissəsi. Rus dili üçün `ru_path`, yoxdursa Azərbaycan yolu —
     * tərcümə edilməmiş düyün ünvansız qalmır.
     */
    public function pathFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === Localization::default()) {
            return (string) $this->path;
        }

        return filled($this->ru_path) ? $this->ru_path : (string) $this->path;
    }

    /** Səhifənin tam URL-i (dil prefiksi ilə) */
    public function urlFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return Localization::categoryUrl($this->pathFor($locale), $locale);
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
            ->withPivot(['sector', 'question_count', 'options_per_question', 'max_score', 'order'])
            ->withTimestamps()
            ->orderBy('category_subject.order');
    }

    /**
     * Sektora aid fənlər. Pivotda `sector` null olan sətirlər hər iki sektora aiddir;
     * ana dili kimi fənlər isə sektora görə ayrı-ayrı bağlanır.
     */
    public function subjectsForSector(string $sector): Collection
    {
        return $this->subjects()
            ->where(fn ($query) => $query
                ->whereNull('category_subject.sector')
                ->orWhere('category_subject.sector', $sector))
            ->get();
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * Ağacın kökü. Valideyn zənciri əvvəlcədən yüklənibsə əlavə sorğu getmir
     * (`with('category.parent.parent')` — ağac üç səviyyədən dərin deyil).
     */
    public function rootAncestor(): Category
    {
        $node = $this;

        while ($node->parent !== null) {
            $node = $node->parent;
        }

        return $node;
    }

    /**
     * Bölmənin rəngi: özününkü, yoxdursa kökündən miras.
     *
     * Rəng yalnız kök düyünlərdə saxlanılır ki, bir bölmənin bütün imtahanları kataloqda
     * eyni rənglə tanınsın; alt düyün istəsə öz rəngini təyin edə bilər.
     *
     * Metodun adı sütunla eyni OLMAMALIDIR: `color` sütunu seçilmədən yüklənmiş modeldə
     * Eloquent `$model->color`-u münasibət (relation) kimi oxumağa çalışar və xəta verər.
     */
    public function displayColor(): ?string
    {
        return $this->color ?: $this->rootAncestor()->color;
    }

    /**
     * Kataloq kartındakı yol: "Sürücülük › DE kateqoriyası".
     * Düyün elə kökdürsə tək ad qaytarılır — eyni söz iki dəfə yazılmır.
     *
     * @return array{root: string, leaf: ?string, color: ?string}
     */
    public function trail(): array
    {
        $root = $this->rootAncestor();

        return [
            'root' => $root->localized('name'),
            'leaf' => $root->is($this) ? null : $this->localized('name'),
            'color' => $this->displayColor(),
        ];
    }

    /**
     * Kateqoriyanın adı sinif səviyyəsini özü bildirirmi? ("9-cu sinif buraxılış", "11 illik")
     *
     * Belə kateqoriyada sinif etiketi ARTIQDIR: kataloqda "9-cu sinif buraxılış" bölməsi ilə
     * "9-cu sinif" etiketi yan-yana düşür və fərqi anlaşılmır. Ona görə həmin səhifədə
     * "Sinif" filtri gizlədilir, admin formasında isə xəbərdarlıq göstərilir.
     *
     * Yol da yoxlanılır: valideyn zəncirini daşıdığı üçün adı neytral olan alt düyün də
     * ("mekteb/9-cu-sinif-buraxilis/…") düzgün tutulur.
     */
    public function mentionsGrade(): bool
    {
        return GradeMention::inAny((string) $this->name, (string) $this->path);
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
