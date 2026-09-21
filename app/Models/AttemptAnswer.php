<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id', 'question_id', 'selected_option_id', 'open_answer', 'is_correct',
        'grade_ratio', 'graded_by', 'graded_at', 'score_earned',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'score_earned' => 'decimal:2',
        'grade_ratio' => 'float',
        'graded_at' => 'datetime',
    ];

    /** Yazılı cavabı qiymətləndirən admin */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }
}
