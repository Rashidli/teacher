<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    /** Açıq (yazılı) suallar admin tərəfindən yoxlanmayıb: bal müvəqqətidir */
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_TIMED_OUT = 'timed_out';

    protected $fillable = [
        'user_id', 'exam_id', 'group_id', 'status', 'started_at', 'finished_at', 'graded_at',
        'time_spent_seconds', 'total_score', 'relative_score', 'correct_answers',
        'wrong_answers', 'unanswered',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'graded_at' => 'datetime',
        'total_score' => 'decimal:2',
        'relative_score' => 'decimal:2',
        'time_spent_seconds' => 'integer',
        'correct_answers' => 'integer',
        'wrong_answers' => 'integer',
        'unanswered' => 'integer',
    ];

    protected $appends = ['remaining_time'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Cəhd başlayanda dondurulmuş sual siyahısı. İmtahanın sual dəsti sonradan dəyişsə də
     * bu cəhdin nəticəsi dəyişmir.
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'attempt_questions', 'attempt_id', 'question_id')
            ->withPivot('order')
            ->withTimestamps()
            ->orderBy('attempt_questions.order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function getRemainingTimeAttribute(): int
    {
        if ($this->status !== 'in_progress') {
            return 0;
        }

        $totalSeconds = $this->exam->duration_minutes * 60;
        $elapsed = (int) $this->started_at->diffInSeconds(now(), false);

        // Əgər elapsed mənfidirsə (timezone problemi), 0 qəbul et
        if ($elapsed < 0) {
            $elapsed = 0;
        }

        $remaining = $totalSeconds - $elapsed;

        return (int) max(0, $remaining);
    }

    public function getProgressPercentageAttribute(): float
    {
        $totalQuestions = $this->exam->questions()->count();
        if ($totalQuestions === 0) return 0;

        $answeredQuestions = $this->answers()->whereNotNull('selected_option_id')->count();
        return round(($answeredQuestions / $totalQuestions) * 100, 1);
    }
}
