<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Question extends Model
{
    use HasFactory;

    /** Variantlı test: variant sayı imtahan səviyyəsində (exams.options_per_question) */
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    /** Qısa/rəqəm cavab — AnswerNormalizer ilə avtomatik yoxlanır */
    public const TYPE_OPEN_CODED = 'open_coded';

    /** Həll yazılır — admin əl ilə qiymətləndirir */
    public const TYPE_OPEN_WRITTEN = 'open_written';

    public const TYPES = [
        self::TYPE_MULTIPLE_CHOICE,
        self::TYPE_OPEN_CODED,
        self::TYPE_OPEN_WRITTEN,
    ];

    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HARD = 'hard';

    public const DIFFICULTIES = [self::DIFFICULTY_EASY, self::DIFFICULTY_MEDIUM, self::DIFFICULTY_HARD];

    protected $fillable = [
        'subject_id', 'topic_id', 'question_text', 'question_image', 'question_image_alt', 'type', 'difficulty',
        'language', 'translation_group_id', 'accepted_answers', 'explanation', 'grading_rubric',
        'source', 'is_active',
        'is_demo',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'accepted_answers' => 'array',
    ];

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
}
