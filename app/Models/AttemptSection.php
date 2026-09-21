<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cəhdin bölmə üzrə nəticəsi. Dəyərlər hesablama anında dondurulur (max_score və NB daxil),
 * ona görə bal matrisi sonradan dəyişsə də köhnə nəticə eyni qalır.
 */
class AttemptSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id', 'section_id', 'subject_id', 'title', 'max_score', 'question_count',
        'correct_answers', 'wrong_answers', 'unanswered', 'relative_score', 'subject_score', 'order',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'relative_score' => 'decimal:2',
        'subject_score' => 'decimal:2',
        'question_count' => 'integer',
        'correct_answers' => 'integer',
        'wrong_answers' => 'integer',
        'unanswered' => 'integer',
        'order' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
