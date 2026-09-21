<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Question extends Model
{
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

    protected $fillable = [
        'exam_id', 'question_text', 'question_image', 'type', 'accepted_answers',
        'explanation', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'accepted_answers' => 'array',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function correctOption(): HasOne
    {
        return $this->hasOne(QuestionOption::class)->where('is_correct', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
