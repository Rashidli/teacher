<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    use HasFactory;

    /** Variantlı test: variant sayı imtahan səviyyəsində (exams.options_per_question) */
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    /** Kodlaşdırılan açıq tapşırıq — avtomatik yoxlanır (bax `CODED_SUBTYPES`) */
    public const TYPE_OPEN_CODED = 'open_coded';

    /** Həll yazılır — meyarla qiymətləndirilir (admin və ya AI) */
    public const TYPE_OPEN_WRITTEN = 'open_written';

    public const TYPES = [
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_OPEN_CODED,
        self::TYPE_OPEN_WRITTEN,
    ];

    /*
     * DİM-in KODLAŞDIRILAN açıq tapşırıqları (fənn üzrə 5 ədəd). Variantlar verilir, cavab
     * kodlaşdırılır və AVTOMATİK yoxlanılır; xam dəyəri qapalı sual kimi 1 baldır.
     * Yoxlama qaydası `App\Support\CodedAnswer`-dədir.
     */

    /** Hesablama: rəqəm və ya qısa mətn cavabı (köhnə davranış — `subtype` boş olanda da bu) */
    public const CODED_NUMERIC = 'numeric';

    /** Seçim: bir neçə düzgün variant; cavab sıralanmış hərflərdir */
    public const CODED_MULTI_SELECT = 'multi_select';

    /** Xronologiya/ardıcıllıq: variantlar düzgün sıraya düzülür */
    public const CODED_ORDERING = 'ordering';

    /** Uyğunluğu müəyyənetmə: sol sütunun hər bəndinə sağdan biri seçilir */
    public const CODED_MATCHING = 'matching';

    public const CODED_SUBTYPES = [
        self::CODED_NUMERIC,
        self::CODED_MULTI_SELECT,
        self::CODED_ORDERING,
        self::CODED_MATCHING,
    ];

    /*
     * DİM-in YAZILI tapşırıqları (fənn üzrə 3 ədəd). Alt növ yalnız MƏLUMAT və FİLTR
     * üçündür — bal qaydasını dəyişmir (hamısı `scoring.open_written_weight` ilə gedir).
     *
     * 2027 modeli: I qrupda (riyaziyyat, fizika, kimya, informatika) yazılı tapşırıqlar
     * sərbəstdir və riyaziyyatdan biri isbata aiddir; II qrupda riyaziyyat, II–III qrupda
     * coğrafiya, IV qrupda fizika/kimya/biologiya situasiyaya; III qrupda dil və ədəbiyyat
     * mətnə; II–III qrupda tarix mənbəyə əsaslanır.
     */

    /** Sərbəst həll (I qrup: riyaziyyat, fizika, kimya, informatika) */
    public const WRITTEN_FREE = 'serbest';

    /** Situasiya əsaslı (riyaziyyat II, coğrafiya II–III, fizika/kimya/biologiya IV) */
    public const WRITTEN_SITUATION = 'situasiya';

    /** Mətnə əsaslanan (III qrup: dil və ədəbiyyat) — mətn `passage`-dədir */
    public const WRITTEN_TEXT = 'metn';

    /** Mənbəyə əsaslanan (II–III qrup: tarix) — mənbə `passage`-dədir */
    public const WRITTEN_SOURCE = 'menbe';

    /** İsbat (I qrup riyaziyyat) */
    public const WRITTEN_PROOF = 'isbat';

    public const WRITTEN_SUBTYPES = [
        self::WRITTEN_FREE,
        self::WRITTEN_SITUATION,
        self::WRITTEN_TEXT,
        self::WRITTEN_SOURCE,
        self::WRITTEN_PROOF,
    ];

    /** Mətn/mənbə tələb edən yazılı alt növlər */
    public const PASSAGE_SUBTYPES = [self::WRITTEN_TEXT, self::WRITTEN_SOURCE];

    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HARD = 'hard';

    public const DIFFICULTIES = [self::DIFFICULTY_EASY, self::DIFFICULTY_MEDIUM, self::DIFFICULTY_HARD];

    protected $fillable = [
        'subject_id', 'topic_id', 'passage_id', 'question_text', 'question_image', 'question_image_alt',
        'type', 'subtype', 'difficulty',
        'language', 'translation_group_id', 'accepted_answers', 'pairs', 'explanation', 'grading_rubric',
        'source', 'is_active',
        'is_demo',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'accepted_answers' => 'array',
        'pairs' => 'array',
    ];

    /**
     * Model şablona bütöv göndəriləndə (admin redaktə formaları) şəkil URL-i də getsin —
     * əks halda hər səhifə yolu əl ilə birləşdirməli olardı.
     */
    protected $appends = ['question_image_url'];

    public function getQuestionImageUrlAttribute(): ?string
    {
        return $this->imageUrl();
    }

    /**
     * Sual şəklinin ictimai URL-i.
     *
     * URL ƏL İLƏ BİRLƏŞDİRİLMİR (`/storage/`.$path): disk konfiqurasiyası dəyişsə
     * (məsələn `APP_URL`, `filesystems.disks.public.url` və ya CDN) əl ilə qurulmuş yol
     * səhv olardı. `Storage::disk('public')->url()` həmişə konfiqurasiyadan gəlir.
     */
    public function imageUrl(): ?string
    {
        return filled($this->question_image)
            ? Storage::disk('public')->url($this->question_image)
            : null;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /** Sual bir neçə imtahanda işlənə bilər; sıra pivotdadır */
    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class)->withPivot('order')->withTimestamps();
    }

    /** Cəhdlərdə istifadə olunubmu (silmə qadağası və redaktə xəbərdarlığı üçün) */
    public function attemptUsageCount(): int
    {
        return DB::table('attempt_questions')->where('question_id', $this->id)->count();
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function correctOption(): HasOne
    {
        return $this->hasOne(QuestionOption::class)->where('is_correct', true);
    }

    /** Eyni sualın digər dildəki versiyaları */
    public function translations()
    {
        return $this->translation_group_id
            ? static::where('translation_group_id', $this->translation_group_id)->whereKeyNot($this->id)
            : static::whereRaw('1 = 0');
    }

    public function scopeLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** `DemoContentSeeder`-in yaratdığı nümunə suallar (`demo:clear` bunları silir) */
    public function scopeDemo($query)
    {
        return $query->where('is_demo', true);
    }

    public function scopeMultipleChoice($query)
    {
        return $query->where('type', self::TYPE_MULTIPLE_CHOICE);
    }

    public function scopeOpenCoded($query)
    {
        return $query->where('type', self::TYPE_OPEN_CODED);
    }

    /** Əl ilə qiymətləndirilən suallar: cəhd bunlar yoxlanana qədər tam bal almır */
    public function scopeOpenWritten($query)
    {
        return $query->where('type', self::TYPE_OPEN_WRITTEN);
    }

    /** Sual avtomatik yoxlanırmı (yəni admin müdaxiləsi lazım deyilmi)? */
    public function isAutoGraded(): bool
    {
        return $this->type !== self::TYPE_OPEN_WRITTEN;
    }

    /** Mətn/mənbə əsaslı sualın mətni (bir mətn bir neçə suala bağlana bilər) */
    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    /**
     * Kodlaşdırılan sualın alt növü. Köhnə suallarda `subtype` boşdur — onlar hesablama
     * (rəqəm/qısa mətn) sayılır, yəni davranış dəyişmir.
     */
    public function codedSubtype(): ?string
    {
        if ($this->type !== self::TYPE_OPEN_CODED) {
            return null;
        }

        return in_array($this->subtype, self::CODED_SUBTYPES, true)
            ? $this->subtype
            : self::CODED_NUMERIC;
    }

    /**
     * Tipə uyğun alt növlər.
     *
     * @return array<int, string>
     */
    public static function subtypesFor(?string $type): array
    {
        return match ($type) {
            self::TYPE_OPEN_CODED => self::CODED_SUBTYPES,
            self::TYPE_OPEN_WRITTEN => self::WRITTEN_SUBTYPES,
            default => [],
        };
    }

    /** Alt növə görə süzgəc (kataloq və bank filtrləri üçün) */
    public function scopeSubtype($query, string $subtype)
    {
        return $query->where('subtype', $subtype);
    }
}
