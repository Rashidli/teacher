<?php

namespace App\Models;

use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    /** Tam model sınaq imtahanı */
    public const KIND_GENERAL = 'general';

    /** Rüb üzrə mövzu sınağı */
    public const KIND_TOPIC_TRIAL = 'topic_trial';

    /** Tək fənn üzrə sınaq */
    public const KIND_SUBJECT = 'subject';

    /** Məşq testi (taymersiz) */
    public const KIND_PRACTICE = 'practice';

    public const KINDS = [self::KIND_GENERAL, self::KIND_TOPIC_TRIAL, self::KIND_SUBJECT, self::KIND_PRACTICE];


    protected $fillable = [
        'teacher_id', 'created_by', 'subject_id', 'group_id', 'category_id', 'kind', 'sector', 'quarter', 'is_cumulative',
        'slug', 'title', 'description',
        'duration_minutes', 'options_per_question', 'price', 'is_free', 'is_active', 'is_published',
        'published_at', 'created_by_admin', 'is_demo',
    ];

    /**
     * İctimai ünvan (`/imtahan/{slug}`) yalnız YARADILANDA qurulur.
     *
     * Başlıq sonradan düzəldiləndə slug dəyişmir: dərc olunmuş imtahanın ünvanı
     * paylaşılmış, indeksləşmiş və yadda saxlanılmış ola bilər.
     */
    protected static function booted(): void
    {
        static::creating(function (Exam $exam) {
            if (blank($exam->slug)) {
                $exam->slug = Slug::unique(
                    (string) $exam->title,
                    fn (string $candidate) => static::withTrashed()->where('slug', $candidate)->exists(),
                );
            }
        });
    }

    protected $casts = [
        'price' => 'decimal:2',
        'is_free' => 'boolean',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'duration_minutes' => 'integer',
        'options_per_question' => 'integer',
        'created_by_admin' => 'boolean',
        'is_demo' => 'boolean',
        'quarter' => 'integer',
        'is_cumulative' => 'boolean',
    ];

    /** Müəllim modulunda imtahanın aid olduğu müəllim (modul söndürülübsə NULL) */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** İmtahanı yaradan istifadəçi — rolundan asılı olmayaraq (admin və ya müəllim) */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** İmtahanın fənn bölmələri (tək-fənli imtahanda da bir bölmə olur) */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('order');
    }

    /** İmtahanın sualları bankdan gəlir; sıra pivotdadır */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class)
            ->withPivot(['order', 'section_id'])
            ->withTimestamps()
            ->orderBy('exam_question.section_id')
            ->orderBy('exam_question.order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }


    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** `DemoContentSeeder`-in yaratdığı nümunə imtahanlar (`demo:clear` bunları silir) */
    public function scopeDemo($query)
    {
        return $query->where('is_demo', true);
    }

    public function scopeForSector($query, string $sector)
    {
        return $query->where('sector', $sector);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function scopeBySubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    /**
     * Kataloq fənn filtri: imtahanın BÖLMƏLƏRİNƏ baxır, `exams.subject_id`-ə yox.
     *
     * Çoxfənli imtahan (məs. I qrup mövzu sınağı) içindəki istənilən fənnə görə tapılmalıdır;
     * `exams.subject_id` isə yalnız birinci bölmənin fənnidir.
     */
    public function hasSubject(int $subjectId): bool
    {
        return $this->sections->contains(fn (ExamSection $section) => (int) $section->subject_id === $subjectId);
    }

    /** Kataloqda və ictimai səhifədə görünən imtahanlar */
    public function scopeVisible($query, string $sector)
    {
        return $query->where('sector', $sector)
            ->where('is_published', true)
            ->where('is_active', true);
    }

    /** İctimai səhifənin tam ünvanı (verilən dil prefiksi ilə) */
    public function publicUrl(?string $locale = null): string
    {
        return \App\Support\Localization::route('exam.show', ['exam' => $this->slug], true, $locale);
    }

    /**
     * İmtahanın TƏK əsas ünvanı — sektoruna görə.
     *
     * İmtahan məzmunu tərcümə olunmur: az sektoru imtahanı `/imtahan/{slug}`, rus sektoru
     * imtahanı `/ru/imtahan/{slug}` ünvanında yaşayır. Digər dil prefiksi ilə də açılır
     * (interfeys dili üçün), amma canonical həmişə buraya göstərir.
     */
    public function canonicalUrl(): string
    {
        $locale = \App\Support\Localization::isSupported($this->sector)
            ? $this->sector
            : \App\Support\Localization::default();

        return $this->publicUrl($locale);
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeByTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
